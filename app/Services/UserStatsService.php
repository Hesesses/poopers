<?php

namespace App\Services;

use App\Models\LeagueDayResult;
use App\Models\User;

class UserStatsService
{
    /**
     * @return array{total_wins: int, total_losses: int, total_steps: int, leagues_count: int, winning_streak_best: int, not_losing_streak_best: int}
     */
    public function getStats(User $user): array
    {
        $wins = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->where('is_winner', true)
            ->count();

        $losses = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->where('is_last', true)
            ->count();

        $totalSteps = $user->dailySteps()->sum('steps');

        $bestWinningStreak = $user->streaks()
            ->where('type', \App\Enums\StreakType::Winning)
            ->max('best_count') ?? 0;

        $bestNotLosingStreak = $user->streaks()
            ->where('type', \App\Enums\StreakType::NotLosing)
            ->max('best_count') ?? 0;

        return [
            'total_wins' => $wins,
            'total_losses' => $losses,
            'total_steps' => (int) $totalSteps,
            'leagues_count' => $user->leagues()->count(),
            'winning_streak_best' => $bestWinningStreak,
            'not_losing_streak_best' => $bestNotLosingStreak,
        ];
    }
}
