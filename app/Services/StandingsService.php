<?php

namespace App\Services;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemEffectType;
use App\Models\DailySteps;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\LeagueNoonSnapshot;
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
     * @return array{standings: Collection, visibility: string, league_time: string, streaks: Collection}
     */
    public function getToday(League $league, User $currentUser): array
    {
        $leagueTime = now()->setTimezone($league->timezone);
        $hour = $leagueTime->hour;
        $today = $leagueTime->copy()->startOfDay();

        $phase = $this->getVisibilityPhase($hour);

        $members = $league->members()->get();
        $activeEffects = $this->getActiveEffects($league->id, $today);

        // Load live steps (needed for own_steps, expose_steps, evening phase, and hidden phase)
        $liveSteps = DailySteps::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where('date', $today)
            ->get()
            ->keyBy('user_id');

        // Load noon snapshot for noon_reveal phase
        $snapshots = null;
        if ($phase === 'noon_reveal') {
            $snapshots = LeagueNoonSnapshot::query()
                ->where('league_id', $league->id)
                ->where('date', $today)
                ->get()
                ->keyBy('user_id');

            // No snapshot available — fall back to hidden phase
            if ($snapshots->isEmpty()) {
                $phase = 'hidden';
                $snapshots = null;
            }
        }

        // Check once: does current user have All Seeing Eye active?
        $hasAllSeeingEye = $this->hasEffectOnSelf($activeEffects, $currentUser->id, ItemEffectType::SpyAllSteps);

        $standings = $members->map(function (User $member) use ($liveSteps, $snapshots, $currentUser, $phase, $activeEffects, $hasAllSeeingEye) {
            $isSelf = $member->id === $currentUser->id;
            $liveMemberSteps = $liveSteps->get($member->id);
            $liveRealSteps = $liveMemberSteps?->steps ?? 0;
            $liveRealModifiedSteps = $liveMemberSteps?->modified_steps ?? 0;

            // 1. Base visibility from phase
            $showSteps = match ($phase) {
                'hidden' => $isSelf,
                'noon_reveal' => true,
                'evening' => false,
            };
            $showPositions = $phase !== 'hidden';

            // Determine source steps (snapshot or live)
            if ($phase === 'noon_reveal' && $snapshots) {
                $snapshot = $snapshots->get($member->id);
                $sourceSteps = $snapshot?->steps ?? 0;
                $sourceModifiedSteps = $snapshot?->modified_steps ?? 0;
            } else {
                $sourceSteps = $liveRealSteps;
                $sourceModifiedSteps = $liveRealModifiedSteps;
            }

            $displaySteps = $sourceSteps;
            $displayModifiedSteps = $sourceModifiedSteps;

            // 2. spy_all_steps → force show LIVE steps for all members
            if ($hasAllSeeingEye && ! $isSelf) {
                $showSteps = true;
                $displaySteps = $liveRealSteps;
                $displayModifiedSteps = $liveRealModifiedSteps;
            }

            // 3. expose_steps → force show LIVE steps (overrides snapshot too)
            if ($this->hasEffect($activeEffects, $member->id, ItemEffectType::ExposeSteps)) {
                $showSteps = true;
                $displaySteps = $liveRealSteps;
                $displayModifiedSteps = $liveRealModifiedSteps;
            }

            // 4. hide_steps_from_attacker → hide steps from specific viewer
            if (! $isSelf && $this->isHiddenFromUser($activeEffects, $member->id, $currentUser->id)) {
                $showSteps = false;
            }

            // 5. hide_ranking → hide position from target
            if ($isSelf && $this->hasEffect($activeEffects, $currentUser->id, ItemEffectType::HideRanking)) {
                $showPositions = false;
            }

            // 6. fake_steps → apply variance when steps visible to others
            if (! $isSelf && $showSteps && $this->hasEffectOnSelf($activeEffects, $member->id, ItemEffectType::FakeSteps)) {
                $variance = 30;
                $fakeMultiplier = 1 + (random_int(-$variance, $variance) / 100);
                $displaySteps = (int) round($displaySteps * $fakeMultiplier);
                $displayModifiedSteps = (int) round($displayModifiedSteps * $fakeMultiplier);
            }

            // Position from snapshot during noon_reveal
            $position = null;
            if ($phase === 'noon_reveal' && $snapshots) {
                $snapshot = $snapshots->get($member->id);
                $position = $snapshot?->position;
            }

            return (object) [
                'user' => $member,
                'steps' => $showSteps ? $displaySteps : null,
                'modified_steps' => $showSteps ? $displayModifiedSteps : null,
                'show_steps' => $showSteps,
                'show_positions' => $showPositions,
                'is_self' => $isSelf,
                'own_steps' => $isSelf ? $liveRealSteps : null,
                'position' => $position,
                '_modified_steps_for_sort' => $phase === 'evening' ? $liveRealModifiedSteps : ($sourceModifiedSteps ?? 0),
            ];
        });

        // Sort and assign positions based on phase
        if ($phase === 'hidden') {
            // Current user first, rest randomized with stable seed per day
            $self = $standings->filter(fn ($s) => $s->is_self)->values();
            $others = $standings->filter(fn ($s) => ! $s->is_self)->shuffle(crc32($today->toDateString().$league->id));
            $standings = $self->merge($others)->values();
        } elseif ($phase === 'noon_reveal') {
            // Sort by snapshot position
            $standings = $standings->sortBy(fn ($s) => $s->position ?? PHP_INT_MAX)->values();
        } else {
            // Evening: sort by live modified_steps, assign live positions
            $standings = $standings->sortByDesc(fn ($s) => $s->_modified_steps_for_sort)->values();
            $standings->each(function ($standing, $index) {
                $standing->position = $index + 1;
            });
        }

        // Clean up internal sort field
        $standings->each(function ($standing) {
            unset($standing->_modified_steps_for_sort);
        });

        $streaks = Streak::query()
            ->where('league_id', $league->id)
            ->where('user_id', $currentUser->id)
            ->where('current_count', '>', 0)
            ->get();

        return [
            'standings' => $standings,
            'streaks' => $streaks,
            'visibility' => $phase,
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

    private function getVisibilityPhase(int $hour): string
    {
        return match (true) {
            $hour < 12 => 'hidden',
            $hour < 18 => 'noon_reveal',
            default => 'evening',
        };
    }

    /**
     * @return Collection<int, ItemEffect>
     */
    private function getActiveEffects(int $leagueId, Carbon $date): Collection
    {
        return ItemEffect::query()
            ->where('league_id', $leagueId)
            ->where('date', $date)
            ->where('status', ItemEffectStatus::Applied)
            ->with('userItem.item', 'userItem')
            ->get();
    }

    /**
     * @param  Collection<int, ItemEffect>  $effects
     */
    private function hasEffect(Collection $effects, int $targetUserId, ItemEffectType $type): bool
    {
        return $effects->contains(function (ItemEffect $effect) use ($targetUserId, $type) {
            return $effect->target_user_id === $targetUserId
                && ItemEffectType::tryFrom($effect->userItem->item->effect['type'] ?? '') === $type;
        });
    }

    /**
     * @param  Collection<int, ItemEffect>  $effects
     */
    private function hasEffectOnSelf(Collection $effects, int $userId, ItemEffectType $type): bool
    {
        return $effects->contains(function (ItemEffect $effect) use ($userId, $type) {
            return $effect->target_user_id === $userId
                && $effect->userItem->user_id === $userId
                && ItemEffectType::tryFrom($effect->userItem->item->effect['type'] ?? '') === $type;
        });
    }

    /**
     * @param  Collection<int, ItemEffect>  $effects
     */
    private function isHiddenFromUser(Collection $effects, int $targetUserId, int $viewerUserId): bool
    {
        return $effects->contains(function (ItemEffect $effect) use ($viewerUserId) {
            $type = ItemEffectType::tryFrom($effect->userItem->item->effect['type'] ?? '');

            return $type === ItemEffectType::HideStepsFromAttacker
                && $effect->target_user_id === $viewerUserId;
        });
    }
}
