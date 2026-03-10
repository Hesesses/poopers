<?php

namespace App\Jobs;

use App\Models\DailySteps;
use App\Models\League;
use App\Models\LeagueNoonSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TakeNoonSnapshots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $now = now();

        League::query()->with('members')->chunk(100, function ($leagues) use ($now) {
            foreach ($leagues as $league) {
                $leagueTime = $now->copy()->setTimezone($league->timezone);

                if ($leagueTime->hour !== 12) {
                    continue;
                }

                $today = $leagueTime->copy()->startOfDay();

                $steps = DailySteps::query()
                    ->whereIn('user_id', $league->members->pluck('id'))
                    ->where('date', $today)
                    ->get()
                    ->keyBy('user_id');

                $sorted = $league->members
                    ->sortByDesc(fn ($m) => $steps->get($m->id)?->modified_steps ?? 0)
                    ->values();

                $records = $sorted->map(fn ($member, $index) => [
                    'league_id' => $league->id,
                    'user_id' => $member->id,
                    'date' => $today,
                    'steps' => $steps->get($member->id)?->steps ?? 0,
                    'modified_steps' => $steps->get($member->id)?->modified_steps ?? 0,
                    'position' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                LeagueNoonSnapshot::upsert(
                    $records,
                    ['league_id', 'user_id', 'date'],
                    ['steps', 'modified_steps', 'position', 'updated_at']
                );
            }
        });
    }
}
