<?php

namespace App\Services;

use App\Enums\ItemEffectStatus;
use App\Enums\StreakType;
use App\Models\ItemEffect;
use App\Models\LeagueDayResult;
use App\Models\User;

class UserStatsService
{
    /**
     * @return array{total_wins: int, total_losses: int, total_steps: int, leagues_count: int, winning_streak_best: int, not_losing_streak_best: int, days_competed: int, current_win_streak: int, current_not_losing_streak: int, current_pooper_streak: int, most_steps_in_day: int, items_used: int, attacks_sent: int, attacks_blocked: int}
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
            ->where('type', StreakType::Winning)
            ->max('best_count') ?? 0;

        $bestNotLosingStreak = $user->streaks()
            ->where('type', StreakType::NotLosing)
            ->max('best_count') ?? 0;

        $daysCompeted = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->distinct('date')
            ->count('date');

        $currentWinStreak = $user->streaks()
            ->where('type', StreakType::Winning)
            ->max('current_count') ?? 0;

        $currentNotLosingStreak = $user->streaks()
            ->where('type', StreakType::NotLosing)
            ->max('current_count') ?? 0;

        $currentPooperStreak = $user->streaks()
            ->where('type', StreakType::Pooper)
            ->max('current_count') ?? 0;

        $mostStepsInDay = $user->dailySteps()->max('steps') ?? 0;

        $itemsUsed = $user->items()->whereNotNull('used_at')->count();

        $attacksSent = ItemEffect::whereHas('userItem', fn ($q) => $q->where('user_id', $user->id))->count();

        $attacksBlocked = ItemEffect::query()
            ->where('target_user_id', $user->id)
            ->where('status', ItemEffectStatus::Blocked)
            ->count();

        return [
            'total_wins' => $wins,
            'total_losses' => $losses,
            'total_steps' => (int) $totalSteps,
            'leagues_count' => $user->leagues()->count(),
            'winning_streak_best' => $bestWinningStreak,
            'not_losing_streak_best' => $bestNotLosingStreak,
            'days_competed' => $daysCompeted,
            'current_win_streak' => $currentWinStreak,
            'current_not_losing_streak' => $currentNotLosingStreak,
            'current_pooper_streak' => $currentPooperStreak,
            'most_steps_in_day' => (int) $mostStepsInDay,
            'items_used' => $itemsUsed,
            'attacks_sent' => $attacksSent,
            'attacks_blocked' => $attacksBlocked,
        ];
    }
}
