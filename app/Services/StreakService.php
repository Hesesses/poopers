<?php

namespace App\Services;

use App\Enums\StreakType;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\Streak;

class StreakService
{
    public function updateStreaks(League $league, string $date): void
    {
        $results = LeagueDayResult::query()
            ->where('league_id', $league->id)
            ->where('date', $date)
            ->get();

        foreach ($results as $result) {
            $this->updateWinningStreak($result->user_id, $league->id, $result->is_winner);
            $this->updateNotLosingStreak($result->user_id, $league->id, $result->is_last);
            $this->updatePooperStreak($result->user_id, $league->id, $result->is_last);
        }
    }

    private function updateWinningStreak(string $userId, string $leagueId, bool $isWinner): void
    {
        $streak = Streak::query()->firstOrCreate(
            ['user_id' => $userId, 'league_id' => $leagueId, 'type' => StreakType::Winning],
            ['current_count' => 0, 'best_count' => 0],
        );

        if ($isWinner) {
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

    private function updateNotLosingStreak(string $userId, string $leagueId, bool $isLast): void
    {
        $streak = Streak::query()->firstOrCreate(
            ['user_id' => $userId, 'league_id' => $leagueId, 'type' => StreakType::NotLosing],
            ['current_count' => 0, 'best_count' => 0],
        );

        if (! $isLast) {
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

    private function updatePooperStreak(string $userId, string $leagueId, bool $isLast): void
    {
        $streak = Streak::query()->firstOrCreate(
            ['user_id' => $userId, 'league_id' => $leagueId, 'type' => StreakType::Pooper],
            ['current_count' => 0, 'best_count' => 0],
        );

        if ($isLast) {
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
