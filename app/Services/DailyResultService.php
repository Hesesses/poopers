<?php

namespace App\Services;

use App\Enums\ItemSource;
use App\Models\DailySteps;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\User;

class DailyResultService
{
    public function __construct(
        private ItemService $itemService,
        private StreakService $streakService,
    ) {}

    public function calculateForLeague(League $league, string $date, bool $awardItems = true): void
    {
        if (LeagueDayResult::query()->where('league_id', $league->id)->where('date', $date)->exists()) {
            return;
        }

        $league->loadMissing('members');

        $steps = DailySteps::query()
            ->whereIn('user_id', $league->members->pluck('id'))
            ->where('date', $date)
            ->get()
            ->keyBy('user_id');

        $sorted = $league->members->sortByDesc(fn (User $m) => $steps->get($m->id)?->modified_steps ?? 0)->values();

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
                $this->itemService->awardRandomItem($member, $league, ItemSource::DailyWin);
            }
        }

        $this->streakService->updateStreaks($league, $date);
    }
}
