<?php

namespace App\Policies;

use App\Enums\LeagueMemberRole;
use App\Models\League;
use App\Models\User;

class LeaguePolicy
{
    public function view(User $user, League $league): bool
    {
        return $league->members()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, League $league): bool
    {
        return $this->isAdmin($user, $league);
    }

    public function delete(User $user, League $league): bool
    {
        return $this->isAdmin($user, $league);
    }

    public function viewMembers(User $user, League $league): bool
    {
        return $league->members()->where('user_id', $user->id)->exists();
    }

    public function manageMembers(User $user, League $league): bool
    {
        return $this->isAdmin($user, $league);
    }

    public function viewStandings(User $user, League $league): bool
    {
        return $league->members()->where('user_id', $user->id)->exists();
    }

    public function useItems(User $user, League $league): bool
    {
        return $league->members()->where('user_id', $user->id)->exists();
    }

    public function viewDrafts(User $user, League $league): bool
    {
        return $league->members()->where('user_id', $user->id)->exists();
    }

    public function viewInviteCode(User $user, League $league): bool
    {
        return $league->members()->where('user_id', $user->id)->exists();
    }

    public function refreshInviteCode(User $user, League $league): bool
    {
        return $this->isAdmin($user, $league);
    }

    private function isAdmin(User $user, League $league): bool
    {
        $member = $league->leagueMembers()->where('user_id', $user->id)->first();

        return $member && $member->role === LeagueMemberRole::Admin;
    }
}
