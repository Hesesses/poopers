<?php

use App\Enums\LeagueMemberRole;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;

it('lists members', function () {
    [$user, $league] = createLeagueWithMember();

    $this->getJson("/api/leagues/{$league->id}/members")
        ->assertOk()
        ->assertJsonCount(1);
});

it('joins with valid invite code', function () {
    [$admin, $league] = createLeagueWithMember();
    $joiner = actingAsUser();

    $this->postJson("/api/leagues/{$league->id}/join", [
        'invite_code' => $league->invite_code,
    ])->assertOk();

    expect($league->members()->where('user_id', $joiner->id)->exists())->toBeTrue();
});

it('fails with invalid invite code', function () {
    [$admin, $league] = createLeagueWithMember();
    actingAsUser();

    $this->postJson("/api/leagues/{$league->id}/join", [
        'invite_code' => 'WRONG1',
    ])->assertUnprocessable();
});

it('fails if already member', function () {
    [$user, $league] = createLeagueWithMember();

    $this->postJson("/api/leagues/{$league->id}/join", [
        'invite_code' => $league->invite_code,
    ])->assertUnprocessable();
});

it('free user cannot join second league', function () {
    [$user, $league1] = createLeagueWithMember();

    $otherAdmin = User::factory()->create();
    $league2 = League::factory()->create(['created_by' => $otherAdmin->id]);
    LeagueMember::create([
        'league_id' => $league2->id,
        'user_id' => $otherAdmin->id,
        'role' => LeagueMemberRole::Admin,
    ]);

    $this->postJson("/api/leagues/{$league2->id}/join", [
        'invite_code' => $league2->invite_code,
    ])->assertUnprocessable();
});

it('league full rejects join', function () {
    [$admin, $league] = createLeagueWithMember();

    // Add 4 more members to reach max of 5
    for ($i = 0; $i < 4; $i++) {
        $member = User::factory()->create();
        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $member->id,
            'role' => LeagueMemberRole::Member,
        ]);
    }

    $joiner = actingAsUser(['is_pro' => true, 'pro_expires_at' => now()->addYear()]);

    $this->postJson("/api/leagues/{$league->id}/join", [
        'invite_code' => $league->invite_code,
    ])->assertUnprocessable();
});

it('member leaves league', function () {
    [$admin, $league] = createLeagueWithMember();
    $member = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $member->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $this->actingAs($member, 'sanctum');
    $this->postJson("/api/leagues/{$league->id}/leave")
        ->assertOk();

    expect($league->members()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('admin leaving transfers admin', function () {
    [$admin, $league] = createLeagueWithMember();
    $member = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $member->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $this->postJson("/api/leagues/{$league->id}/leave")
        ->assertOk();

    $newAdmin = LeagueMember::where('league_id', $league->id)
        ->where('user_id', $member->id)
        ->first();

    expect($newAdmin->role)->toBe(LeagueMemberRole::Admin);
});

it('last member leaving deletes league', function () {
    [$user, $league] = createLeagueWithMember();

    $this->postJson("/api/leagues/{$league->id}/leave")
        ->assertOk();

    expect($league->fresh()->trashed())->toBeTrue();
});

it('admin removes member', function () {
    [$admin, $league] = createLeagueWithMember();
    $member = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $member->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $this->deleteJson("/api/leagues/{$league->id}/members/{$member->id}")
        ->assertOk();

    expect($league->members()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('non-admin cannot remove member', function () {
    [$admin, $league] = createLeagueWithMember();
    $member = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $member->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $this->actingAs($member, 'sanctum');
    $this->deleteJson("/api/leagues/{$league->id}/members/{$admin->id}")
        ->assertForbidden();
});
