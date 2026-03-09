<?php

namespace App\Services;

use App\Models\DailySteps;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\Streak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StandingsService
{
    public function getMonthStandings(League $league, ?string $yearMonth = null): Collection
    {
        $leagueTime = now()->setTimezone($league->timezone);
        $yearMonth ??= $leagueTime->format('Y-m');
        $today = $leagueTime->toDateString();
        $start = Carbon::parse($yearMonth.'-01')->startOfMonth();
        $end = Carbon::parse($yearMonth.'-01')->endOfMonth();

        return $this->getStandingsWithLiveSteps($league, $start, $end, $today);
    }

    public function getWeekStandings(League $league): Collection
    {
        $leagueTime = now()->setTimezone($league->timezone);
        $today = $leagueTime->toDateString();
        $start = $leagueTime->copy()->startOfWeek();
        $end = $leagueTime->copy()->endOfWeek();

        return $this->getStandingsWithLiveSteps($league, $start, $end, $today);
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

        $streaks = Streak::query()
            ->where('league_id', $league->id)
            ->where('user_id', $currentUser->id)
            ->where('current_count', '>', 0)
            ->get();

        return [
            'standings' => $standings,
            'streaks' => $streaks,
            'visibility' => $visibility,
            'league_time' => $leagueTime->format('H:i'),
        ];
    }

    private function getStandingsWithLiveSteps(League $league, Carbon $start, Carbon $end, string $today): Collection
    {
        $results = LeagueDayResult::query()
            ->where('league_id', $league->id)
            ->whereBetween('date', [$start, $end])
            ->where('date', '!=', $today)
            ->with('user')
            ->get()
            ->groupBy('user_id');

        $members = $league->members()->get();

        $todaySteps = DailySteps::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where('date', $today)
            ->get()
            ->keyBy('user_id');

        return $members->map(function (User $member) use ($results, $todaySteps) {
            $memberResults = $results->get((string) $member->id, collect());
            $todayStep = $todaySteps->get($member->id);

            if ($memberResults->isEmpty() && ! $todayStep) {
                return null;
            }

            return (object) [
                'user' => $member,
                'total_steps' => $memberResults->sum('steps') + ($todayStep?->steps ?? 0),
                'total_modified_steps' => $memberResults->sum('modified_steps') + ($todayStep?->modified_steps ?? 0),
                'wins' => $memberResults->where('is_winner', true)->count(),
                'losses' => $memberResults->where('is_last', true)->count(),
                'days_played' => $memberResults->count() + ($todayStep ? 1 : 0),
            ];
        })->filter()->sortByDesc('wins')->values();
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
