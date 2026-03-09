<?php

namespace App\Services\Items;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemEffectType;
use App\Enums\ItemType;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\StepSyncService;

class DefenseResolver
{
    public function resolve(ItemEffect $attackEffect, UserItem $attackerItem, User $target, League $league): DefenseResult
    {
        $result = new DefenseResult;
        $today = now()->toDateString();

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
            $defType = ItemEffectType::tryFrom($defEffect->userItem->item->effect['type'] ?? '');

            // Block all attacks (Hazmat Suit)
            if ($defType === ItemEffectType::BlockAllAttacks) {
                $result->blocked = true;
                $result->blockedByItemId = $defEffect->userItem->item_id;
                $this->markAttackBlocked($attackEffect, $defEffect->userItem->item_id);

                return $this->applyReactiveEffects($result, $defensiveEffects, $target, $league, $attackerItem);
            }

            // Block all + bonus (Titanium Toilet)
            if ($defType === ItemEffectType::BlockAllAndBonus) {
                $result->blocked = true;
                $result->blockedByItemId = $defEffect->userItem->item_id;
                $this->markAttackBlocked($attackEffect, $defEffect->userItem->item_id);
                $this->createBoostEffect($defEffect->userItem, $target, $league, 3);

                return $this->applyReactiveEffects($result, $defensiveEffects, $target, $league, $attackerItem);
            }

            // Negate + bonus (Golden Throne)
            if ($defType === ItemEffectType::NegateAndBonus) {
                $result->blocked = true;
                $result->blockedByItemId = $defEffect->userItem->item_id;
                $this->markAttackBlocked($attackEffect, $defEffect->userItem->item_id);
                $defEffect->update(['status' => ItemEffectStatus::Consumed]);
                $this->createBoostEffect($defEffect->userItem, $target, $league, 3);

                return $result;
            }

            // Block single attack (Air Freshener)
            if ($defType === ItemEffectType::BlockAttack) {
                $result->blocked = true;
                $result->blockedByItemId = $defEffect->userItem->item_id;
                $this->markAttackBlocked($attackEffect, $defEffect->userItem->item_id);
                $defEffect->update(['status' => ItemEffectStatus::Consumed]);

                return $result;
            }

            // Reflect attack (Bidet Shield)
            if ($defType === ItemEffectType::ReflectAttack) {
                $result->reflected = true;
                $result->blockedByItemId = $defEffect->userItem->item_id;
                $attackEffect->update([
                    'status' => ItemEffectStatus::Reflected,
                    'blocked_by_item_id' => $defEffect->userItem->item_id,
                ]);
                $defEffect->update(['status' => ItemEffectStatus::Consumed]);

                $attacker = $attackerItem->user;
                ItemEffect::query()->create([
                    'user_item_id' => $attackEffect->user_item_id,
                    'target_user_id' => $attacker->id,
                    'league_id' => $league->id,
                    'date' => $today,
                    'status' => ItemEffectStatus::Applied,
                ]);
                app(StepSyncService::class)->recalculateModifiedSteps($attacker, $today);

                return $result;
            }

            // Dodge (Decoy Dump) - 50% miss chance
            if ($defType === ItemEffectType::Dodge) {
                if (random_int(0, 1) === 1) {
                    $result->missed = true;
                    $attackEffect->update(['status' => ItemEffectStatus::Missed]);
                    $defEffect->update(['status' => ItemEffectStatus::Consumed]);

                    return $result;
                }
                $defEffect->update(['status' => ItemEffectStatus::Consumed]);
            }

            // Reduce damage (Wet Wipe) - 50% damage reduction
            if ($defType === ItemEffectType::ReduceDamage) {
                $result->damageMultiplier = 0.5;
                $defEffect->update(['status' => ItemEffectStatus::Consumed]);
            }
        }

        // Apply reactive effects after damage
        return $this->applyReactiveEffects($result, $defensiveEffects, $target, $league, $attackerItem);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ItemEffect>  $defensiveEffects
     */
    private function applyReactiveEffects(
        DefenseResult $result,
        $defensiveEffects,
        User $target,
        League $league,
        UserItem $attackerItem,
    ): DefenseResult {
        $today = now()->toDateString();

        foreach ($defensiveEffects as $defEffect) {
            $defType = ItemEffectType::tryFrom($defEffect->userItem->item->effect['type'] ?? '');

            // Probiotic Shield - +2% boost after attack
            if ($defType === ItemEffectType::ReactiveBonus && $defEffect->status === ItemEffectStatus::Applied) {
                $boostEffect = $this->createBoostEffect($defEffect->userItem, $target, $league, 2);
                $result->reactiveEffects[] = $boostEffect;
            }

            // Odor Shield - hide steps from attacker
            if ($defType === ItemEffectType::HideStepsFromAttacker && $defEffect->status === ItemEffectStatus::Applied) {
                $hideEffect = ItemEffect::query()->create([
                    'user_item_id' => $defEffect->user_item_id,
                    'target_user_id' => $attackerItem->user_id,
                    'league_id' => $league->id,
                    'date' => $today,
                    'status' => ItemEffectStatus::Applied,
                ]);
                $result->reactiveEffects[] = $hideEffect;
            }
        }

        return $result;
    }

    private function markAttackBlocked(ItemEffect $attackEffect, int $blockedByItemId): void
    {
        $attackEffect->update([
            'status' => ItemEffectStatus::Blocked,
            'blocked_by_item_id' => $blockedByItemId,
        ]);
    }

    private function createBoostEffect(UserItem $defenseItem, User $target, League $league, int $percent): ItemEffect
    {
        $effect = ItemEffect::query()->create([
            'user_item_id' => $defenseItem->id,
            'target_user_id' => $target->id,
            'league_id' => $league->id,
            'date' => now()->toDateString(),
            'status' => ItemEffectStatus::Applied,
        ]);

        app(StepSyncService::class)->recalculateModifiedSteps($target, now()->toDateString());

        return $effect;
    }
}
