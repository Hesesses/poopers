<?php

namespace App\Services;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemRarity;
use App\Enums\ItemSource;
use App\Enums\ItemType;
use App\Exceptions\InventoryFullException;
use App\Exceptions\ItemAlreadyUsedTodayException;
use App\Exceptions\ItemWindowClosedException;
use App\Exceptions\ProRequiredException;
use App\Models\Item;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\ItemHandlerRegistry;

class ItemService
{
    public function __construct(
        private NotificationService $notificationService,
        private StepSyncService $stepSyncService,
        private ItemHandlerRegistry $registry,
    ) {}

    /**
     * @return array{effect: ItemEffect, response_data: ?array<string, mixed>}
     */
    public function useItem(UserItem $userItem, User $user, ?User $target, League $league): array
    {
        $item = $userItem->item;
        $handler = $this->registry->resolve($item);

        // Validate not already used
        if ($userItem->isUsed()) {
            throw new ItemAlreadyUsedTodayException('This item has already been used.');
        }

        // Validate not expired
        if ($userItem->isExpired()) {
            throw new ItemAlreadyUsedTodayException('This item has expired.');
        }

        // PRO gating: Rare+ requires Pro
        if (in_array($item->rarity, [ItemRarity::Rare, ItemRarity::Epic, ItemRarity::Legendary]) && ! $user->isPro()) {
            throw new ProRequiredException('This item requires a Pro subscription.');
        }

        // Check offense window (before 18:00 league time)
        if ($item->type === ItemType::Offensive) {
            $leagueTime = now()->setTimezone($league->timezone);
            if ($leagueTime->hour >= 18) {
                throw new ItemWindowClosedException('Offense items must be used before 18:00.');
            }
        }

        // Target validation
        if ($handler->requiresTarget() && ! $target) {
            throw new ItemAlreadyUsedTodayException('This item requires a target.');
        }

        if (! $handler->allowsSelfTarget() && $target && $target->id === $user->id) {
            throw new ItemAlreadyUsedTodayException('You cannot use this item on yourself.');
        }

        // For self-target items, set target to user
        if (! $handler->requiresTarget()) {
            $target = $user;
        }

        // Check for Porta-Potty Trap on user
        $trapTriggered = $this->checkTrap($user, $league);
        if ($trapTriggered) {
            $userItem->update(['used_at' => now()]);

            throw new ItemAlreadyUsedTodayException('A trap was triggered! Your item failed and you lost 5% steps.');
        }

        // Check for Clogged Pipes on user
        if ($this->hasActiveBlock($user, $league)) {
            throw new ItemAlreadyUsedTodayException('Your items are blocked!');
        }

        // Custom handler validation
        $handler->validate($userItem, $user, $target, $league);

        // Mark item as used
        $userItem->update([
            'used_at' => now(),
            'used_on_user_id' => $target?->id,
        ]);

        // Execute handler
        $effect = $handler->execute($userItem, $user, $target, $league);
        $responseData = $handler->getResponseData($effect);

        // Send notifications
        $this->sendNotifications($handler, $effect, $user, $target, $league);

        return [
            'effect' => $effect,
            'response_data' => $responseData,
        ];
    }

    public function awardRandomItem(User $user, League $league, ItemSource $source): UserItem
    {
        $this->checkInventoryLimit($user, $league);

        $item = $this->getRandomItemByRarity();

        return UserItem::query()->create([
            'user_id' => $user->id,
            'league_id' => $league->id,
            'item_id' => $item->id,
            'source' => $source,
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function checkInventoryLimit(User $user, League $league): void
    {
        $unusedCount = UserItem::query()
            ->where('user_id', $user->id)
            ->where('league_id', $league->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->count();

        $limit = $user->isPro() ? 20 : 5;

        if ($unusedCount >= $limit) {
            throw new InventoryFullException("Inventory full ({$limit} items max).");
        }
    }

    private function checkTrap(User $user, League $league): bool
    {
        $trap = ItemEffect::query()
            ->where('target_user_id', $user->id)
            ->where('league_id', $league->id)
            ->where('date', now()->toDateString())
            ->where('status', ItemEffectStatus::Applied)
            ->whereHas('userItem.item', function ($q) {
                $q->whereJsonContains('effect->type', 'set_trap');
            })
            ->first();

        if (! $trap) {
            return false;
        }

        // Trap triggered: consume it and apply -5% penalty
        $trap->update(['status' => ItemEffectStatus::Consumed]);
        $this->stepSyncService->recalculateModifiedSteps($user, now()->toDateString());

        return true;
    }

    private function hasActiveBlock(User $user, League $league): bool
    {
        return ItemEffect::query()
            ->where('target_user_id', $user->id)
            ->where('league_id', $league->id)
            ->where('date', now()->toDateString())
            ->where('status', ItemEffectStatus::Applied)
            ->whereHas('userItem.item', function ($q) {
                $q->whereJsonContains('effect->type', 'block_items');
            })
            ->exists();
    }

    public function hasAnonymousMode(User $user, League $league): bool
    {
        return ItemEffect::query()
            ->whereHas('userItem', fn ($q) => $q->where('user_id', $user->id)->where('league_id', $league->id))
            ->where('target_user_id', $user->id)
            ->where('date', now()->toDateString())
            ->where('status', ItemEffectStatus::Applied)
            ->whereHas('userItem.item', function ($q) {
                $q->whereJsonContains('effect->type', 'anonymous_mode');
            })
            ->exists();
    }

    private function sendNotifications(
        \App\Services\Items\Contracts\ItemHandlerInterface $handler,
        ItemEffect $effect,
        User $user,
        ?User $target,
        League $league,
    ): void {
        $isAnonymous = $this->hasAnonymousMode($user, $league);

        // Notify target
        if ($target && $target->id !== $user->id) {
            $notification = $handler->getTargetNotification($effect, $user);
            if ($notification) {
                $attackerName = $isAnonymous ? 'Someone' : $user->full_name;
                $body = str_replace($user->full_name, $attackerName, $notification['body']);

                $this->notificationService->create(
                    $target,
                    $league,
                    'item_used',
                    "{$notification['title']} [{$league->name}]",
                    $body,
                );
            }
        }

        // Always notify user (DB only, no push — they just used the item)
        $userNotification = $handler->getUserNotification($effect, $target);
        $itemName = $effect->userItem->item->name;
        if ($userNotification) {
            $this->notificationService->create(
                $user,
                $league,
                'item_used',
                "{$userNotification['title']} [{$league->name}]",
                $userNotification['body'],
                sendPush: false,
            );
        } else {
            $body = $target && $target->id !== $user->id
                ? "You used {$itemName} on {$target->full_name}!"
                : "You used {$itemName}!";

            $this->notificationService->create(
                $user,
                $league,
                'item_used',
                "Item Used! [{$league->name}]",
                $body,
                sendPush: false,
            );
        }

        // Notify other league members (only for public items)
        if ($effect->userItem->item->is_public) {
            $attackerName = $isAnonymous ? 'Someone' : $user->full_name;
            $itemName = $effect->userItem->item->name;
            $excludeIds = [$user->id];
            if ($target && $target->id !== $user->id) {
                $excludeIds[] = $target->id;
            }

            $otherMembers = $league->members()->whereNotIn('users.id', $excludeIds)->get();
            $title = "Item Used! [{$league->name}]";
            $body = $target && $target->id !== $user->id
                ? "{$attackerName} used {$itemName} on {$target->full_name}!"
                : "{$attackerName} used {$itemName}!";

            foreach ($otherMembers as $member) {
                $this->notificationService->create(
                    $member,
                    $league,
                    'item_used',
                    $title,
                    $body,
                );
            }
        }
    }

    public function awardWelcomePack(User $user, League $league): void
    {
        $distribution = [
            [ItemType::Offensive, 2],
            [ItemType::Defensive, 1],
            [ItemType::Strategic, 1],
        ];

        $allowedRarities = $user->isPro()
            ? ItemRarity::cases()
            : [ItemRarity::Common, ItemRarity::Uncommon];

        foreach ($distribution as [$type, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $item = $this->getRandomItemByTypeAndRarity($type, $allowedRarities);

                if (! $item) {
                    continue;
                }

                UserItem::query()->create([
                    'user_id' => $user->id,
                    'league_id' => $league->id,
                    'item_id' => $item->id,
                    'source' => ItemSource::Welcome,
                    'expires_at' => now()->addDays(7),
                ]);
            }
        }
    }

    private function getRandomItemByTypeAndRarity(ItemType $type, array $allowedRarities): ?Item
    {
        $rarity = $this->rollRarity($allowedRarities);

        return Item::query()
            ->where('type', $type)
            ->where('rarity', $rarity)
            ->where('slug', '!=', 'all_seeing_eye')
            ->inRandomOrder()
            ->first()
            ?? Item::query()
                ->where('type', $type)
                ->where('slug', '!=', 'all_seeing_eye')
                ->whereIn('rarity', $allowedRarities)
                ->inRandomOrder()
                ->first();
    }

    private function rollRarity(array $allowedRarities): ItemRarity
    {
        $roll = random_int(1, 100);

        $rarity = match (true) {
            $roll <= 60 => ItemRarity::Common,
            $roll <= 90 => ItemRarity::Uncommon,
            $roll <= 97 => ItemRarity::Rare,
            $roll <= 99 => ItemRarity::Epic,
            default => ItemRarity::Legendary,
        };

        if (! in_array($rarity, $allowedRarities)) {
            return $allowedRarities[array_rand($allowedRarities)];
        }

        return $rarity;
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
