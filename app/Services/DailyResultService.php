<?php

namespace App\Services;

use App\Enums\ItemSource;
use App\Exceptions\InventoryFullException;
use App\Models\DailySteps;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\User;
use App\Services\Items\MidnightResolver;

class DailyResultService
{
    public function calculateForLeague(League $league, string $date, bool $awardItems = true, bool $recalculate = false): void
    {
        $existingResults = LeagueDayResult::query()->where('league_id', $league->id)->where('date', $date);

        if ($existingResults->exists()) {
            if (! $recalculate) {
                return;
            }
            $existingResults->delete();
        }

        $league->loadMissing('members');

        $members = $league->members->filter(
            fn (User $member) => $member->pivot->created_at->toDateString() <= $date
        );

        $steps = DailySteps::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where('date', $date)
            ->get()
            ->keyBy('user_id');

        // Step 1: Apply midnight step modifications
        $midnightResolver = app(MidnightResolver::class);
        $midnightResolver->applyStepModifications($members, $steps, $league, $date);

        // Refresh steps after modifications
        $steps = DailySteps::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where('date', $date)
            ->get()
            ->keyBy('user_id');

        // Step 2: Calculate positions
        $sorted = $members->sortByDesc(fn (User $m) => $steps->get($m->id)?->modified_steps ?? 0)->values();

        if ($sorted->isEmpty()) {
            return;
        }

        $topSteps = $steps->get($sorted->first()->id)?->modified_steps ?? 0;
        $bottomSteps = $steps->get($sorted->last()->id)?->modified_steps ?? 0;

        $positions = [];
        foreach ($sorted as $position => $member) {
            $memberSteps = $steps->get($member->id);
            $memberModifiedSteps = $memberSteps?->modified_steps ?? 0;

            $isWinner = $memberModifiedSteps === $topSteps && $topSteps > 0;
            $isLast = $memberModifiedSteps === $bottomSteps && $sorted->count() > 1;

            if ($topSteps === $bottomSteps) {
                $isLast = false;
            }

            $positions[] = [
                'user_id' => $member->id,
                'position' => $position + 1,
                'steps' => $memberSteps?->steps ?? 0,
                'modified_steps' => $memberModifiedSteps,
                'is_winner' => $isWinner,
                'is_last' => $isLast,
            ];
        }

        // Step 3: Apply placement swaps
        $midnightResolver->applyPlacementSwaps($positions, $league, $date);

        // Re-derive winner/last after swaps
        usort($positions, fn ($a, $b) => $a['position'] <=> $b['position']);

        // Step 4: Persist results
        foreach ($positions as $pos) {
            LeagueDayResult::query()->create([
                'league_id' => $league->id,
                'user_id' => $pos['user_id'],
                'date' => $date,
                'steps' => $pos['steps'],
                'modified_steps' => $pos['modified_steps'],
                'position' => $pos['position'],
                'is_winner' => $pos['is_winner'],
                'is_last' => $pos['is_last'],
            ]);

            if ($awardItems && $pos['is_winner']) {
                try {
                    app(ItemService::class)->awardRandomItem(
                        User::query()->find($pos['user_id']),
                        $league,
                        ItemSource::DailyWin,
                    );
                } catch (InventoryFullException) {
                    // Inventory full — skip awarding item
                }
            }
        }

        // Step 5: Update streaks with multiplier for Poop Streak
        app(StreakService::class)->updateStreaks($league, $date, $midnightResolver);
    }
}
