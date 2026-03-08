<?php

namespace App\Services;

use App\Enums\LeagueMemberRole;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LeagueService
{
    public function create(User $user, string $name, string $icon = '💩', string $timezone = 'UTC'): League
    {
        // Free tier: max 1 league
        if (! $user->isPro() && $user->leagues()->count() >= 1) {
            throw ValidationException::withMessages([
                'league' => 'Free users can only be in 1 league. Upgrade to PRO for unlimited leagues.',
            ]);
        }

        $league = League::query()->create([
            'name' => $name,
            'icon' => $icon,
            'timezone' => $timezone,
            'invite_code' => League::generateInviteCode(),
            'created_by' => $user->id,
        ]);

        LeagueMember::query()->create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role' => LeagueMemberRole::Admin,
        ]);

        return $league;
    }

    public function join(User $user, League $league, string $inviteCode): void
    {
        if ($league->invite_code !== $inviteCode) {
            throw ValidationException::withMessages([
                'invite_code' => 'Invalid invite code.',
            ]);
        }

        if ($league->members()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'league' => 'You are already a member of this league.',
            ]);
        }

        // Free tier: max 1 league
        if (! $user->isPro() && $user->leagues()->count() >= 1) {
            throw ValidationException::withMessages([
                'league' => 'Free users can only be in 1 league. Upgrade to PRO for unlimited leagues.',
            ]);
        }

        $currentMemberCount = $league->memberCount();

        // Max 5 for free leagues, 20 for pro leagues
        $maxMembers = $league->is_pro_league ? 20 : 5;
        if ($currentMemberCount >= $maxMembers) {
            throw ValidationException::withMessages([
                'league' => "This league is full (max {$maxMembers} members).",
            ]);
        }

        // 6+ members require PRO for everyone
        if ($currentMemberCount >= 5) {
            if (! $user->isPro()) {
                throw ValidationException::withMessages([
                    'league' => 'Leagues with 6+ members require all members to have PRO.',
                ]);
            }

            // Check all existing members are PRO
            $nonProMembers = $league->members()->get()->filter(fn (User $m) => ! $m->isPro());
            if ($nonProMembers->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'league' => 'All existing members must have PRO to grow past 5 members.',
                ]);
            }

            // Mark as pro league
            if (! $league->is_pro_league) {
                $league->update(['is_pro_league' => true]);
            }
        }

        LeagueMember::query()->create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role' => LeagueMemberRole::Member,
        ]);
    }

    public function leave(User $user, League $league): void
    {
        $member = LeagueMember::query()
            ->where('league_id', $league->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'league' => 'You are not a member of this league.',
            ]);
        }

        // If admin is leaving and there are other members, transfer admin
        if ($member->isAdmin()) {
            $otherMember = LeagueMember::query()
                ->where('league_id', $league->id)
                ->where('user_id', '!=', $user->id)
                ->oldest()
                ->first();

            if ($otherMember) {
                $otherMember->update(['role' => LeagueMemberRole::Admin]);
            } else {
                // Last member leaving, soft delete the league
                $league->delete();
            }
        }

        $member->delete();

        // Check if league should be downgraded from pro
        if ($league->is_pro_league && $league->memberCount() < 6) {
            $league->update(['is_pro_league' => false]);
        }
    }

    public function removeMember(User $admin, League $league, User $target): void
    {
        $member = LeagueMember::query()
            ->where('league_id', $league->id)
            ->where('user_id', $target->id)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user' => 'User is not a member of this league.',
            ]);
        }

        if ($member->user_id === $admin->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot remove yourself.',
            ]);
        }

        $member->delete();

        if ($league->is_pro_league && $league->memberCount() < 6) {
            $league->update(['is_pro_league' => false]);
        }
    }

    public function refreshInviteCode(League $league): string
    {
        $code = League::generateInviteCode();
        $league->update(['invite_code' => $code]);

        return $code;
    }
}
