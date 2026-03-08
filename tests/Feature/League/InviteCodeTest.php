<?php

use App\Enums\LeagueMemberRole;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;

it('member can view invite code', function () {
    [$user, $league] = createLeagueWithMember();

    $this->getJson("/api/leagues/{$league->id}/invite-code")
        ->assertOk()
        ->assertJsonStructure(['invite_code']);
});

it('non-member cannot view invite code', function () {
    actingAsUser();
    $league = League::factory()->create();

    $this->getJson("/api/leagues/{$league->id}/invite-code")
        ->assertForbidden();
});

it('admin can refresh invite code', function () {
    [$user, $league] = createLeagueWithMember();
    $oldCode = $league->invite_code;

    $response = $this->postJson("/api/leagues/{$league->id}/invite-code/refresh");

    $response->assertOk()->assertJsonStructure(['invite_code']);
    expect($response->json('invite_code'))->not->toBe($oldCode);
});

it('non-admin cannot refresh invite code', function () {
    [$admin, $league] = createLeagueWithMember();
    $member = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $member->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $this->actingAs($member, 'sanctum');
    $this->postJson("/api/leagues/{$league->id}/invite-code/refresh")
        ->assertForbidden();
});
