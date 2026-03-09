<?php

namespace App\Services;

use App\Enums\ItemEffectStatus;
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
        $signupDate = $user->created_at->toDateString();

        $wins = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->where('date', '>=', $signupDate)
            ->where('is_winner', true)
            ->count();

        $losses = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->where('date', '>=', $signupDate)
            ->where('is_last', true)
            ->count();

        $totalSteps = $user->dailySteps()
            ->where('date', '>=', $signupDate)
            ->sum('steps');

        $daysCompeted = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->where('date', '>=', $signupDate)
            ->distinct('date')
            ->count('date');

        $mostStepsInDay = $user->dailySteps()
            ->where('date', '>=', $signupDate)
            ->max('steps') ?? 0;

        $streaks = $this->computeStreaks($user, $signupDate);

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
            'winning_streak_best' => $streaks['best_win'],
            'not_losing_streak_best' => $streaks['best_not_losing'],
            'days_competed' => $daysCompeted,
            'current_win_streak' => $streaks['current_win'],
            'current_not_losing_streak' => $streaks['current_not_losing'],
            'current_pooper_streak' => $streaks['current_pooper'],
            'most_steps_in_day' => (int) $mostStepsInDay,
            'items_used' => $itemsUsed,
            'attacks_sent' => $attacksSent,
            'attacks_blocked' => $attacksBlocked,
        ];
    }

    /**
     * Compute streaks from LeagueDayResult data, only counting results after signup.
     *
     * @return array{current_win: int, current_not_losing: int, current_pooper: int, best_win: int, best_not_losing: int}
     */
    private function computeStreaks(User $user, string $signupDate): array
    {
        $results = LeagueDayResult::query()
            ->where('user_id', $user->id)
            ->where('date', '>=', $signupDate)
            ->orderBy('date')
            ->get()
            ->groupBy('league_id');

        $bestWin = 0;
        $bestNotLosing = 0;
        $currentWin = 0;
        $currentNotLosing = 0;
        $currentPooper = 0;

        foreach ($results as $leagueResults) {
            $cWin = 0;
            $bWin = 0;
            $cNotLosing = 0;
            $bNotLosing = 0;
            $cPooper = 0;

            foreach ($leagueResults as $result) {
                if ($result->is_winner) {
                    $cWin++;
                    $bWin = max($bWin, $cWin);
                } else {
                    $cWin = 0;
                }

                if (! $result->is_last) {
                    $cNotLosing++;
                    $bNotLosing = max($bNotLosing, $cNotLosing);
                } else {
                    $cNotLosing = 0;
                }

                $cPooper = $result->is_last ? $cPooper + 1 : 0;
            }

            $bestWin = max($bestWin, $bWin);
            $bestNotLosing = max($bestNotLosing, $bNotLosing);
            $currentWin = max($currentWin, $cWin);
            $currentNotLosing = max($currentNotLosing, $cNotLosing);
            $currentPooper = max($currentPooper, $cPooper);
        }

        return [
            'current_win' => $currentWin,
            'current_not_losing' => $currentNotLosing,
            'current_pooper' => $currentPooper,
            'best_win' => $bestWin,
            'best_not_losing' => $bestNotLosing,
        ];
    }
}
