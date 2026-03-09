<?php

namespace App\Console\Commands;

use App\Enums\StreakType;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\LeagueMember;
use App\Models\Streak;
use Illuminate\Console\Command;

class CleanupInvalidResults extends Command
{
    protected $signature = 'results:cleanup';

    protected $description = 'Remove day results created before users joined their leagues and recalculate streaks';

    public function handle(): int
    {
        $deleted = $this->deleteInvalidResults();
        $this->info("Deleted {$deleted} invalid day results.");

        $this->recalculateStreaks();
        $this->info('Streaks recalculated.');

        return self::SUCCESS;
    }

    private function deleteInvalidResults(): int
    {
        $memberships = LeagueMember::all();
        $deleted = 0;

        foreach ($memberships as $membership) {
            $joinDate = $membership->created_at->toDateString();

            $deleted += LeagueDayResult::query()
                ->where('league_id', $membership->league_id)
                ->where('user_id', $membership->user_id)
                ->where('date', '<', $joinDate)
                ->delete();
        }

        return $deleted;
    }

    private function recalculateStreaks(): void
    {
        Streak::query()->truncate();

        $leagues = League::all();

        foreach ($leagues as $league) {
            $dates = LeagueDayResult::query()
                ->where('league_id', $league->id)
                ->select('date')
                ->distinct()
                ->orderBy('date')
                ->pluck('date');

            foreach ($dates as $date) {
                $results = LeagueDayResult::query()
                    ->where('league_id', $league->id)
                    ->where('date', $date)
                    ->get();

                foreach ($results as $result) {
                    $this->updateStreak($result->user_id, $league->id, StreakType::Winning, $result->is_winner);
                    $this->updateStreak($result->user_id, $league->id, StreakType::NotLosing, ! $result->is_last);
                    $this->updateStreak($result->user_id, $league->id, StreakType::Pooper, $result->is_last);
                }
            }
        }
    }

    private function updateStreak(string $userId, string $leagueId, StreakType $type, bool $shouldIncrement): void
    {
        $streak = Streak::query()->firstOrCreate(
            ['user_id' => $userId, 'league_id' => $leagueId, 'type' => $type],
            ['current_count' => 0, 'best_count' => 0],
        );

        if ($shouldIncrement) {
            $streak->increment('current_count');
            if ($streak->started_at === null) {
                $streak->update(['started_at' => now()->toDateString()]);
            }
            if ($streak->current_count > $streak->best_count) {
                $streak->update(['best_count' => $streak->current_count]);
            }
        } else {
            $streak->update(['current_count' => 0, 'started_at' => null]);
        }
    }
}
