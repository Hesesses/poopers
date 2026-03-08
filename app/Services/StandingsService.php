<?php

namespace App\Services;

use App\Models\DailySteps;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StandingsService
{
    public function getMonthStandings(League $league, ?string $yearMonth = null): Collection
    {
        $yearMonth ??= now()->setTimezone($league->timezone)->format('Y-m');
        $start = Carbon::parse($yearMonth.'-01')->startOfMonth();
        $end = Carbon::parse($yearMonth.'-01')->endOfMonth();

        return LeagueDayResult::query()
            ->where('league_id', $league->id)
            ->whereBetween('date', [$start, $end])
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $results, string $userId) {
                $user = $results->first()->user;

                return (object) [
                    'user' => $user,
                    'total_steps' => $results->sum('steps'),
                    'total_modified_steps' => $results->sum('modified_steps'),
                    'wins' => $results->where('is_winner', true)->count(),
                    'losses' => $results->where('is_last', true)->count(),
                    'days_played' => $results->count(),
                ];
            })
            ->sortByDesc('wins')
            ->values();
    }

    public function getWeekStandings(League $league): Collection
    {
        $leagueTime = now()->setTimezone($league->timezone);
        $start = $leagueTime->copy()->startOfWeek();
        $end = $leagueTime->copy()->endOfWeek();

        return LeagueDayResult::query()
            ->where('league_id', $league->id)
            ->whereBetween('date', [$start, $end])
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $results, string $userId) {
                $user = $results->first()->user;

                return (object) [
                    'user' => $user,
                    'total_steps' => $results->sum('steps'),
                    'total_modified_steps' => $results->sum('modified_steps'),
                    'wins' => $results->where('is_winner', true)->count(),
                    'losses' => $results->where('is_last', true)->count(),
                    'days_played' => $results->count(),
                ];
            })
            ->sortByDesc('wins')
            ->values();
    }

    public function getYesterdayResults(League $league): Collection
    {
        $yesterday = now()->setTimezone($league->timezone)->subDay()->toDateString();

        return LeagueDayResult::query()
            ->where('league_id', $league->id)
            ->where('date', $yesterday)
            ->with('user')
            ->orderBy('position')
            ->get();
    }

    /**
     * Get today's live data with visibility rules based on league timezone.
     *
     * @return array{standings: Collection, visibility: string, snapshot_time: ?string}
     */
    public function getToday(League $league, User $currentUser): array
    {
        $leagueTime = now()->setTimezone($league->timezone);
        $hour = $leagueTime->hour;
        $today = $leagueTime->toDateString();

        $visibility = $this->getVisibilityLevel($hour);

        $members = $league->members()->get();
        $steps = DailySteps::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where('date', $today)
            ->get()
            ->keyBy('user_id');

        $standings = $members->map(function (User $member) use ($steps, $currentUser, $visibility) {
            $memberSteps = $steps->get($member->id);

            $showSteps = match ($visibility) {
                'own_only' => $member->id === $currentUser->id,
                'snapshot' => true,
                default => false,
            };

            $showPositions = $visibility !== 'own_only';

            return (object) [
                'user' => $member,
                'steps' => $showSteps ? ($memberSteps?->steps ?? 0) : null,
                'modified_steps' => $showSteps ? ($memberSteps?->modified_steps ?? 0) : null,
                'show_steps' => $showSteps,
                'show_positions' => $showPositions,
                'is_self' => $member->id === $currentUser->id,
                'own_steps' => $member->id === $currentUser->id ? ($memberSteps?->steps ?? 0) : null,
            ];
        })->sortByDesc(fn ($s) => $s->modified_steps ?? 0)->values();

        // Assign positions
        $standings->each(function ($standing, $index) {
            $standing->position = $index + 1;
        });

        return [
            'standings' => $standings,
            'visibility' => $visibility,
            'league_time' => $leagueTime->format('H:i'),
        ];
    }

    private function getVisibilityLevel(int $hour): string
    {
        if ($hour >= 0 && $hour < 8) {
            return 'own_only';
        }

        if ($hour === 12 || $hour === 18) {
            return 'snapshot';
        }

        return 'positions_only';
    }
}
