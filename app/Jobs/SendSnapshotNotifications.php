<?php

namespace App\Jobs;

use App\Models\DailySteps;
use App\Models\League;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSnapshotNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        $now = now();

        League::query()->with('members')->chunk(100, function ($leagues) use ($now, $notificationService) {
            foreach ($leagues as $league) {
                $leagueTime = $now->copy()->setTimezone($league->timezone);
                $hour = $leagueTime->hour;

                if ($hour !== 12 && $hour !== 18) {
                    continue;
                }

                $today = $now->toDateString();
                $steps = DailySteps::query()
                    ->whereIn('user_id', $league->members->pluck('id'))
                    ->where('date', $today)
                    ->get()
                    ->keyBy('user_id');

                $sorted = $league->members->sortByDesc(fn ($m) => $steps->get($m->id)?->modified_steps ?? 0)->values();

                $leader = $sorted->first();
                $leaderSteps = $steps->get($leader?->id)?->modified_steps ?? 0;

                $type = $hour === 12 ? 'midday_snapshot' : 'evening_snapshot';
                $title = $hour === 12 ? "Midday Snapshot [{$league->name}]" : "Evening Snapshot [{$league->name}]";

                foreach ($league->members as $member) {
                    $position = $sorted->search(fn ($m) => $m->id === $member->id) + 1;
                    $body = $hour === 12
                        ? "You're #{$position}. {$leader->full_name} leads with {$leaderSteps} steps."
                        : "You're #{$position} in {$league->name}. Keep going!";

                    $notificationService->create($member, $league, $type, $title, $body);
                }
            }
        });
    }
}
