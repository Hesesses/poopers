<?php

namespace App\Services;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemEffectType;
use App\Models\DailySteps;
use App\Models\ItemEffect;
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
     * @return array{standings: Collection, visibility: string, league_time: string, streaks: Collection}
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

        // Load active item effects for visibility modifications
        $activeEffects = $this->getActiveEffects($league->id, $today);

        $standings = $members->map(function (User $member) use ($steps, $currentUser, $visibility, $activeEffects) {
            $memberSteps = $steps->get($member->id);
            $realSteps = $memberSteps?->steps ?? 0;
            $realModifiedSteps = $memberSteps?->modified_steps ?? 0;

            $showSteps = match ($visibility) {
                'own_only' => $member->id === $currentUser->id,
                'snapshot' => true,
                default => false,
            };

            $showPositions = $visibility !== 'own_only';

            // Toilet Paper Trail: expose target's steps to everyone
            if ($this->hasEffect($activeEffects, $member->id, ItemEffectType::ExposeSteps)) {
                $showSteps = true;
            }

            // The Brown Out: hide position from target
            if ($member->id === $currentUser->id && $this->hasEffect($activeEffects, $currentUser->id, ItemEffectType::HideRanking)) {
                $showPositions = false;
            }

            // Fake Poop: show fake steps to others
            $displaySteps = $realSteps;
            $displayModifiedSteps = $realModifiedSteps;
            if ($member->id !== $currentUser->id && $this->hasEffectOnSelf($activeEffects, $member->id, ItemEffectType::FakeSteps)) {
                $variance = 30;
                $fakeMultiplier = 1 + (random_int(-$variance, $variance) / 100);
                $displaySteps = (int) round($realSteps * $fakeMultiplier);
                $displayModifiedSteps = (int) round($realModifiedSteps * $fakeMultiplier);
            }

            // Odor Shield: hide steps from specific attackers
            if ($member->id !== $currentUser->id && $this->isHiddenFromUser($activeEffects, $member->id, $currentUser->id)) {
                $showSteps = false;
            }

            return (object) [
                'user' => $member,
                'steps' => $showSteps ? $displaySteps : null,
                'modified_steps' => $showSteps ? $displayModifiedSteps : null,
                'show_steps' => $showSteps,
                'show_positions' => $showPositions,
                'is_self' => $member->id === $currentUser->id,
                'own_steps' => $member->id === $currentUser->id ? $realSteps : null,
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

    /**
     * @return Collection<int, ItemEffect>
     */
    private function getActiveEffects(string $leagueId, string $date): Collection
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
    private function hasEffect(Collection $effects, string $targetUserId, ItemEffectType $type): bool
    {
        return $effects->contains(function (ItemEffect $effect) use ($targetUserId, $type) {
            return $effect->target_user_id === $targetUserId
                && ItemEffectType::tryFrom($effect->userItem->item->effect['type'] ?? '') === $type;
        });
    }

    /**
     * Check if user has an effect they placed on themselves.
     *
     * @param  Collection<int, ItemEffect>  $effects
     */
    private function hasEffectOnSelf(Collection $effects, string $userId, ItemEffectType $type): bool
    {
        return $effects->contains(function (ItemEffect $effect) use ($userId, $type) {
            return $effect->target_user_id === $userId
                && $effect->userItem->user_id === $userId
                && ItemEffectType::tryFrom($effect->userItem->item->effect['type'] ?? '') === $type;
        });
    }

    /**
     * Check if a user's steps are hidden from a specific viewer (Odor Shield).
     *
     * @param  Collection<int, ItemEffect>  $effects
     */
    private function isHiddenFromUser(Collection $effects, string $targetUserId, string $viewerUserId): bool
    {
        return $effects->contains(function (ItemEffect $effect) use ($viewerUserId) {
            $type = ItemEffectType::tryFrom($effect->userItem->item->effect['type'] ?? '');

            // Odor Shield creates effects where target_user_id is the attacker to hide from
            return $type === ItemEffectType::HideStepsFromAttacker
                && $effect->target_user_id === $viewerUserId;
        });
    }
}
