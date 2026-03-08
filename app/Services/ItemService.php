<?php

namespace App\Services;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemRarity;
use App\Enums\ItemSource;
use App\Enums\ItemType;
use App\Exceptions\ItemAlreadyUsedTodayException;
use App\Exceptions\ItemWindowClosedException;
use App\Models\Item;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;

class ItemService
{
    public function __construct(
        private NotificationService $notificationService,
        private StepSyncService $stepSyncService,
    ) {}

    public function useItem(UserItem $userItem, User $user, User $target, League $league): ItemEffect
    {
        $item = $userItem->item;

        // Validate not already used
        if ($userItem->isUsed()) {
            throw new ItemAlreadyUsedTodayException('This item has already been used.');
        }

        // Validate not expired
        if ($userItem->isExpired()) {
            throw new ItemAlreadyUsedTodayException('This item has expired.');
        }

        // Check if user already used an item today in this league
        $usedToday = UserItem::query()
            ->where('user_id', $user->id)
            ->where('league_id', $league->id)
            ->whereNotNull('used_at')
            ->whereDate('used_at', now()->toDateString())
            ->where('id', '!=', $userItem->id)
            ->exists();

        if ($usedToday) {
            throw new ItemAlreadyUsedTodayException('You can only use 1 item per day.');
        }

        // Check offense window (before 18:00 league time)
        if ($item->type === ItemType::Offensive) {
            $leagueTime = now()->setTimezone($league->timezone);
            if ($leagueTime->hour >= 18) {
                throw new ItemWindowClosedException('Offense items must be used before 18:00.');
            }
        }

        // Mark item as used
        $userItem->update([
            'used_at' => now(),
            'used_on_user_id' => $target->id,
        ]);

        // Create effect
        $effect = ItemEffect::query()->create([
            'user_item_id' => $userItem->id,
            'target_user_id' => $target->id,
            'league_id' => $league->id,
            'date' => now()->toDateString(),
            'status' => ItemEffectStatus::Pending,
        ]);

        // Handle item type logic
        if ($item->type === ItemType::Offensive) {
            $effect = $this->handleOffensiveItem($effect, $userItem, $target, $league);
        } elseif ($item->type === ItemType::Defensive) {
            $effect = $this->handleDefensiveItem($effect, $userItem, $user);
        } elseif ($item->type === ItemType::Strategic) {
            $effect = $this->handleStrategicItem($effect, $userItem, $user, $target, $league);
        }

        return $effect;
    }

    private function handleOffensiveItem(ItemEffect $effect, UserItem $userItem, User $target, League $league): ItemEffect
    {
        // Check for defensive items on target
        $blocked = $this->checkDefensiveItems($effect, $target, $league);

        if (! $blocked) {
            $effect->update(['status' => ItemEffectStatus::Applied]);
            $this->stepSyncService->recalculateModifiedSteps($target, now()->toDateString());
        }

        // Notify target
        $this->notificationService->create(
            $target,
            $league,
            'attack_received',
            'You were attacked!',
            "{$userItem->user->full_name} used {$userItem->item->name} on you!",
        );

        return $effect->fresh();
    }

    private function handleDefensiveItem(ItemEffect $effect, UserItem $userItem, User $user): ItemEffect
    {
        $effect->update(['status' => ItemEffectStatus::Applied]);

        return $effect->fresh();
    }

    /**
     * @return array{steps: ?int, inventory: ?array<mixed>}|null
     */
    private function handleStrategicItem(ItemEffect $effect, UserItem $userItem, User $user, User $target, League $league): ItemEffect
    {
        $effect->update(['status' => ItemEffectStatus::Applied]);

        return $effect->fresh();
    }

    /**
     * @return array{steps: ?int}|null
     */
    public function handleSpyItem(UserItem $userItem, User $target, League $league): ?array
    {
        $item = $userItem->item;
        $effectType = $item->effect['type'] ?? null;

        return match ($effectType) {
            'spy_single' => ['steps' => $target->dailySteps()->where('date', now()->toDateString())->value('steps')],
            'spy_inventory' => [
                'inventory' => UserItem::query()
                    ->where('league_id', $league->id)
                    ->whereNull('used_at')
                    ->where('expires_at', '>', now())
                    ->with(['item', 'user'])
                    ->get()
                    ->groupBy('user_id')
                    ->toArray(),
            ],
            default => null,
        };
    }

    private function checkDefensiveItems(ItemEffect $effect, User $target, League $league): bool
    {
        $today = now()->toDateString();

        // Check for active defensive effects on target
        $defensiveEffects = ItemEffect::query()
            ->whereHas('userItem', function ($q) use ($target, $league) {
                $q->where('user_id', $target->id)
                    ->where('league_id', $league->id)
                    ->whereHas('item', fn ($iq) => $iq->where('type', ItemType::Defensive));
            })
            ->where('date', $today)
            ->where('status', ItemEffectStatus::Applied)
            ->with('userItem.item')
            ->get();

        foreach ($defensiveEffects as $defEffect) {
            $defItem = $defEffect->userItem->item;
            $defEffectType = $defItem->effect['type'] ?? null;

            if ($defEffectType === 'block_attack' || $defEffectType === 'block_all_attacks') {
                $effect->update([
                    'status' => ItemEffectStatus::Blocked,
                    'blocked_by_item_id' => $defEffect->userItem->item_id,
                ]);

                // Single-use block is consumed
                if ($defEffectType === 'block_attack') {
                    $defEffect->update(['status' => ItemEffectStatus::Blocked]);
                }

                return true;
            }

            if ($defEffectType === 'reflect_attack') {
                $effect->update([
                    'status' => ItemEffectStatus::Reflected,
                    'blocked_by_item_id' => $defEffect->userItem->item_id,
                ]);

                // Apply the attack to the original attacker
                $attacker = $effect->userItem->user;
                ItemEffect::query()->create([
                    'user_item_id' => $effect->user_item_id,
                    'target_user_id' => $attacker->id,
                    'league_id' => $league->id,
                    'date' => $today,
                    'status' => ItemEffectStatus::Applied,
                ]);

                $this->stepSyncService->recalculateModifiedSteps($attacker, $today);
                $defEffect->update(['status' => ItemEffectStatus::Blocked]);

                return true;
            }
        }

        return false;
    }

    public function awardRandomItem(User $user, League $league, ItemSource $source): UserItem
    {
        $item = $this->getRandomItemByRarity();

        return UserItem::query()->create([
            'user_id' => $user->id,
            'league_id' => $league->id,
            'item_id' => $item->id,
            'source' => $source,
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function getRandomItemByRarity(): Item
    {
        $roll = random_int(1, 100);

        $rarity = match (true) {
            $roll <= 60 => ItemRarity::Common,
            $roll <= 90 => ItemRarity::Uncommon,
            $roll <= 97 => ItemRarity::Rare,
            $roll <= 99 => ItemRarity::Epic,
            default => ItemRarity::Legendary,
        };

        return Item::query()
            ->where('rarity', $rarity)
            ->inRandomOrder()
            ->first() ?? Item::query()->inRandomOrder()->first();
    }
}
