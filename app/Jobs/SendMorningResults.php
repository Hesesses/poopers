<?php

namespace App\Jobs;

use App\Models\League;
use App\Models\LeagueDayResult;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMorningResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        $now = now();

        League::query()->with('members')->chunk(100, function ($leagues) use ($now, $notificationService) {
            foreach ($leagues as $league) {
                $leagueTime = $now->copy()->setTimezone($league->timezone);

                if ($leagueTime->hour !== 8) {
                    continue;
                }

                $yesterday = $leagueTime->copy()->subDay()->toDateString();

                $winner = LeagueDayResult::query()
                    ->where('league_id', $league->id)
                    ->where('date', $yesterday)
                    ->where('is_winner', true)
                    ->with('user')
                    ->first();

                if (! $winner) {
                    continue;
                }

                foreach ($league->members as $member) {
                    $notificationService->create(
                        $member,
                        $league,
                        'daily_results',
                        'Yesterday\'s Results',
                        "The results are in for {$league->name}! Tap to see who won 🏆 and who's the pooper 💩",
                        ['league_id' => $league->id],
                    );
                }
            }
        });
    }
}
