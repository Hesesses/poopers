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
    public function getStats(User $user, ?int $leagueId = null): array
    {
        $signupDate = $user->created_at->toDateString();

        $wins = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->where('date', '>=', $signupDate)
            ->where('is_winner', true)
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->count();

        $losses = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->where('date', '>=', $signupDate)
            ->where('is_last', true)
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->count();

        $totalSteps = $user->dailySteps()
            ->where('date', '>=', $signupDate)
            ->sum('steps');

        $bestWinningStreak = $user->streaks()
            ->where('type', StreakType::Winning)
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->max('best_count') ?? 0;

        $bestNotLosingStreak = $user->streaks()
            ->where('type', StreakType::NotLosing)
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->max('best_count') ?? 0;

        $daysCompeted = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->where('date', '>=', $signupDate)
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->distinct('date')
            ->count('date');

        $currentWinStreak = $user->streaks()
            ->where('type', StreakType::Winning)
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->max('current_count') ?? 0;

        $currentNotLosingStreak = $user->streaks()
            ->where('type', StreakType::NotLosing)
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->max('current_count') ?? 0;

        $currentPooperStreak = $user->streaks()
            ->where('type', StreakType::Pooper)
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->max('current_count') ?? 0;

        $mostStepsInDay = $user->dailySteps()
            ->where('date', '>=', $signupDate)
            ->max('steps') ?? 0;

        $itemsUsed = $user->items()
            ->whereNotNull('used_at')
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->count();

        $attacksSent = ItemEffect::query()
            ->whereHas('userItem', fn ($q) => $q->where('user_id', $user->id))
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
            ->count();

        $attacksBlocked = ItemEffect::query()
            ->where('target_user_id', $user->id)
            ->where('status', ItemEffectStatus::Blocked)
            ->when($leagueId, fn ($q) => $q->where('league_id', $leagueId))
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
