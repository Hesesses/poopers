<?php

namespace App\Jobs;

use App\Enums\ItemSource;
use App\Models\DailySteps;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\User;
use App\Services\ItemService;
use App\Services\StreakService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateDailyResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ItemService $itemService, StreakService $streakService): void
    {
        $now = now();

        League::query()->with('members')->chunk(100, function ($leagues) use ($now, $itemService, $streakService) {
            foreach ($leagues as $league) {
                $leagueTime = $now->copy()->setTimezone($league->timezone);

                // Only process at midnight in league's timezone
                if ($leagueTime->hour !== 0) {
                    continue;
                }

                $yesterday = $leagueTime->copy()->subDay()->toDateString();

                // Skip if already calculated
                if (LeagueDayResult::query()->where('league_id', $league->id)->where('date', $yesterday)->exists()) {
                    continue;
                }

                $this->calculateForLeague($league, $yesterday, $itemService, $streakService);
            }
        });
    }

    private function calculateForLeague(League $league, string $date, ItemService $itemService, StreakService $streakService): void
    {
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

            // Handle ties: both tied at top = both win, both tied at bottom = both last
            $isWinner = $memberModifiedSteps === $topSteps && $topSteps > 0;
            $isLast = $memberModifiedSteps === $bottomSteps && $sorted->count() > 1;

            // If everyone tied, no one is last
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

            // Award item to winners
            if ($isWinner) {
                $itemService->awardRandomItem($member, $league, ItemSource::DailyWin);
            }
        }

        $streakService->updateStreaks($league, $date);
    }
}
