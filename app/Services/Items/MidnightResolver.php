<?php

namespace App\Services\Items;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemEffectType;
use App\Models\DailySteps;
use App\Models\ItemEffect;
use App\Models\League;

class MidnightResolver
{
    /**
     * Apply step modifications before position calculation.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\User>  $members
     * @param  \Illuminate\Support\Collection<int, DailySteps>  $steps  keyed by user_id
     */
    public function applyStepModifications($members, $steps, League $league, string $date): void
    {
        $effects = ItemEffect::query()
            ->where('league_id', $league->id)
            ->where('date', $date)
            ->where('status', ItemEffectStatus::Applied)
            ->with('userItem.item')
            ->get();

        foreach ($effects as $effect) {
            $effectType = ItemEffectType::tryFrom($effect->userItem->item->effect['type'] ?? '');
            $dailySteps = $steps->get($effect->target_user_id);

            if (! $dailySteps) {
                continue;
            }

            match ($effectType) {
                // Fiber Boost: +5% to modified_steps
                ItemEffectType::BoostSteps => $this->applyPercentModification($dailySteps, $effect->userItem->item->effect['value'] ?? 5),

                // Septic Tank: -1% per 1000 raw steps, max -15%
                ItemEffectType::ScalingReduction => $this->applyScalingReduction($dailySteps),

                // Double or Nothing: coin flip +10% or -10%
                ItemEffectType::CoinFlip => $this->applyCoinFlip($dailySteps, $effect),

                // Timed Boost (Morning Coffee): bonus from hourly_steps pre-noon
                ItemEffectType::TimedBoost => $this->applyTimedBoost($dailySteps),

                default => null,
            };
        }
    }

    /**
     * Apply placement swaps after position calculation.
     *
     * @param  array<int, array{user_id: string, position: int}>  $positions  mutable reference
     */
    public function applyPlacementSwaps(array &$positions, League $league, string $date): void
    {
        $effects = ItemEffect::query()
            ->where('league_id', $league->id)
            ->where('date', $date)
            ->where('status', ItemEffectStatus::Applied)
            ->with('userItem.item', 'userItem')
            ->get();

        // Upper Decker: swap user ↔ target
        foreach ($effects as $effect) {
            $effectType = ItemEffectType::tryFrom($effect->userItem->item->effect['type'] ?? '');

            if ($effectType === ItemEffectType::SwapPlacement) {
                $attackerId = $effect->userItem->user_id;
                $targetId = $effect->target_user_id;
                $this->swapPositions($positions, $attackerId, $targetId);
                $effect->update(['status' => ItemEffectStatus::Consumed]);
            }
        }

        // Royal Flush: swap 1st ↔ last
        foreach ($effects as $effect) {
            $effectType = ItemEffectType::tryFrom($effect->userItem->item->effect['type'] ?? '');

            if ($effectType === ItemEffectType::SwapFirstLast) {
                $firstUserId = null;
                $lastUserId = null;
                $minPos = PHP_INT_MAX;
                $maxPos = 0;

                foreach ($positions as $pos) {
                    if ($pos['position'] < $minPos) {
                        $minPos = $pos['position'];
                        $firstUserId = $pos['user_id'];
                    }
                    if ($pos['position'] > $maxPos) {
                        $maxPos = $pos['position'];
                        $lastUserId = $pos['user_id'];
                    }
                }

                if ($firstUserId && $lastUserId && $firstUserId !== $lastUserId) {
                    $this->swapPositions($positions, $firstUserId, $lastUserId);
                }
                $effect->update(['status' => ItemEffectStatus::Consumed]);
            }
        }
    }

    /**
     * Check for Poop Streak effect and return streak multiplier.
     */
    public function getStreakMultiplier(string $userId, string $leagueId, string $date): int
    {
        $hasDoubleStreak = ItemEffect::query()
            ->where('league_id', $leagueId)
            ->where('date', $date)
            ->where('target_user_id', $userId)
            ->where('status', ItemEffectStatus::Applied)
            ->whereHas('userItem.item', function ($q) {
                $q->whereJsonContains('effect->type', 'double_streak');
            })
            ->exists();

        return $hasDoubleStreak ? 2 : 1;
    }

    private function applyPercentModification(DailySteps $dailySteps, int $percent): void
    {
        $boost = (int) round($dailySteps->modified_steps * $percent / 100);
        $dailySteps->update(['modified_steps' => $dailySteps->modified_steps + $boost]);
    }

    private function applyScalingReduction(DailySteps $dailySteps): void
    {
        $reductionPercent = min(15, (int) floor($dailySteps->steps / 1000));
        $reduction = (int) round($dailySteps->modified_steps * $reductionPercent / 100);
        $dailySteps->update(['modified_steps' => max(0, $dailySteps->modified_steps - $reduction)]);
    }

    private function applyCoinFlip(DailySteps $dailySteps, ItemEffect $effect): void
    {
        $won = random_int(0, 1) === 1;
        $percent = $won ? 10 : -10;
        $change = (int) round($dailySteps->modified_steps * $percent / 100);
        $dailySteps->update(['modified_steps' => max(0, $dailySteps->modified_steps + $change)]);
    }

    private function applyTimedBoost(DailySteps $dailySteps): void
    {
        $hourlySteps = $dailySteps->hourly_steps ?? [];
        $morningSteps = 0;

        for ($i = 0; $i < 12; $i++) {
            $morningSteps += $hourlySteps[$i] ?? 0;
        }

        $bonus = (int) round($morningSteps * 0.1);
        $dailySteps->update(['modified_steps' => $dailySteps->modified_steps + $bonus]);
    }

    /**
     * @param  array<int, array{user_id: string, position: int}>  $positions
     */
    private function swapPositions(array &$positions, string $userIdA, string $userIdB): void
    {
        $posA = null;
        $posB = null;
        $indexA = null;
        $indexB = null;

        foreach ($positions as $i => $pos) {
            if ($pos['user_id'] === $userIdA) {
                $posA = $pos['position'];
                $indexA = $i;
            }
            if ($pos['user_id'] === $userIdB) {
                $posB = $pos['position'];
                $indexB = $i;
            }
        }

        if ($indexA !== null && $indexB !== null) {
            $positions[$indexA]['position'] = $posB;
            $positions[$indexB]['position'] = $posA;
        }
    }
}
