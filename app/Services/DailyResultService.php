<?php

namespace App\Services;

use App\Enums\ItemSource;
use App\Models\DailySteps;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\User;

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

        $sorted = $members->sortByDesc(fn (User $m) => $steps->get($m->id)?->modified_steps ?? 0)->values();

        if ($sorted->isEmpty()) {
            return;
        }

        $topSteps = $steps->get($sorted->first()->id)?->modified_steps ?? 0;
        $bottomSteps = $steps->get($sorted->last()->id)?->modified_steps ?? 0;

        foreach ($sorted as $position => $member) {
            $memberSteps = $steps->get($member->id);
            $memberModifiedSteps = $memberSteps?->modified_steps ?? 0;

            $isWinner = $memberModifiedSteps === $topSteps && $topSteps > 0;
            $isLast = $memberModifiedSteps === $bottomSteps && $sorted->count() > 1;

            if ($topSteps === $bottomSteps) {
                $isLast = false;
            }

            LeagueDayResult::query()->create([
                'league_id' => $league->id,
                'user_id' => $member->id,
                'date' => $date,
                'steps' => $memberSteps?->steps ?? 0,
                'modified_steps' => $memberModifiedSteps,
                'position' => $position + 1,
                'is_winner' => $isWinner,
                'is_last' => $isLast,
            ]);

            if ($awardItems && $isWinner) {
                app(ItemService::class)->awardRandomItem($member, $league, ItemSource::DailyWin);
            }
        }

        app(StreakService::class)->updateStreaks($league, $date);
    }
}
