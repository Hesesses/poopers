<?php

use App\Enums\LeagueMemberRole;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

function actingAsUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    test()->actingAs($user, 'sanctum');

    return $user;
}

function createLeagueWithMember(?User $user = null): array
{
    $user ??= actingAsUser();
    $league = League::factory()->create(['created_by' => $user->id]);
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $user->id,
        'role' => LeagueMemberRole::Admin,
    ]);

    return [$user, $league];
}
