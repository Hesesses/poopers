<?php

namespace App\Jobs;

use App\Models\League;
use App\Services\DailyResultService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateDailyResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DailyResultService $dailyResultService): void
    {
        $now = now();

        League::query()->with('members')->chunk(100, function ($leagues) use ($now, $dailyResultService) {
            foreach ($leagues as $league) {
                $leagueTime = $now->copy()->setTimezone($league->timezone);

                // Only process at midnight in league's timezone
                if ($leagueTime->hour !== 0) {
                    continue;
                }

                $yesterday = $leagueTime->copy()->subDay()->toDateString();

                $dailyResultService->calculateForLeague($league, $yesterday);
            }
        });
    }
}
