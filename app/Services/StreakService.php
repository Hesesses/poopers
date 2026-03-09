<?php

namespace App\Services;

use App\Enums\StreakType;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\Streak;
use App\Services\Items\MidnightResolver;

class StreakService
{
    public function updateStreaks(League $league, string $date, ?MidnightResolver $midnightResolver = null): void
    {
        $results = LeagueDayResult::query()
            ->where('league_id', $league->id)
            ->where('date', $date)
            ->get();

        foreach ($results as $result) {
            $this->updateStreak($result->user_id, $league->id, StreakType::Winning, $result->is_winner);
            $this->updateStreak($result->user_id, $league->id, StreakType::NotLosing, ! $result->is_last);

            // Check for Poop Streak multiplier
            $multiplier = 1;
            if ($result->is_last && $midnightResolver) {
                $multiplier = $midnightResolver->getStreakMultiplier($result->user_id, $league->id, $date);
            }

            $this->updateStreak($result->user_id, $league->id, StreakType::Pooper, $result->is_last, $multiplier);
        }
    }

    private function updateStreak(string $userId, string $leagueId, StreakType $type, bool $shouldIncrement, int $incrementBy = 1): void
    {
        $streak = Streak::query()->firstOrCreate(
            ['user_id' => $userId, 'league_id' => $leagueId, 'type' => $type],
            ['current_count' => 0, 'best_count' => 0],
        );

        if ($shouldIncrement) {
            $streak->increment('current_count', $incrementBy);
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
