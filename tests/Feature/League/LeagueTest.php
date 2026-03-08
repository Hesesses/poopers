<?php

use App\Models\League;

it('lists user leagues', function () {
    [$user, $league] = createLeagueWithMember();

    $this->getJson('/api/leagues')
        ->assertOk()
        ->assertJsonCount(1);
});

it('creates league', function () {
    $user = actingAsUser();

    $response = $this->postJson('/api/leagues', ['name' => 'Test League']);

    $response->assertCreated();
    expect(League::where('name', 'Test League')->exists())->toBeTrue();
    expect($user->leagues()->count())->toBe(1);
});

it('free user cannot create second league', function () {
    [$user, $league] = createLeagueWithMember();

    $this->postJson('/api/leagues', ['name' => 'Second League'])
        ->assertUnprocessable();
});

it('pro user can create multiple leagues', function () {
    $user = actingAsUser(['is_pro' => true, 'pro_expires_at' => now()->addYear()]);

    $this->postJson('/api/leagues', ['name' => 'League 1'])->assertCreated();
    $this->postJson('/api/leagues', ['name' => 'League 2'])->assertCreated();
});

it('shows league details', function () {
    [$user, $league] = createLeagueWithMember();

    $this->getJson("/api/leagues/{$league->id}")
        ->assertOk()
        ->assertJsonFragment(['name' => $league->name])
        ->assertJsonStructure(['data' => ['id', 'name', 'icon', 'timezone']]);
});

it('non-member cannot view league', function () {
    $otherUser = actingAsUser();
    $league = League::factory()->create();

    $this->getJson("/api/leagues/{$league->id}")
        ->assertForbidden();
});

it('admin updates league', function () {
    [$user, $league] = createLeagueWithMember();

    $this->putJson("/api/leagues/{$league->id}", ['name' => 'Updated Name'])
        ->assertOk();

    expect($league->fresh()->name)->toBe('Updated Name');
});

it('non-admin cannot update league', function () {
    [$admin, $league] = createLeagueWithMember();
    $member = \App\Models\User::factory()->create();
    \App\Models\LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $member->id,
        'role' => \App\Enums\LeagueMemberRole::Member,
    ]);

    $this->actingAs($member, 'sanctum');
    $this->putJson("/api/leagues/{$league->id}", ['name' => 'Hacked'])
        ->assertForbidden();
});

it('admin deletes league', function () {
    [$user, $league] = createLeagueWithMember();

    $this->deleteJson("/api/leagues/{$league->id}")
        ->assertOk();

    expect($league->fresh()->trashed())->toBeTrue();
});

it('non-admin cannot delete league', function () {
    [$admin, $league] = createLeagueWithMember();
    $member = \App\Models\User::factory()->create();
    \App\Models\LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $member->id,
        'role' => \App\Enums\LeagueMemberRole::Member,
    ]);

    $this->actingAs($member, 'sanctum');
    $this->deleteJson("/api/leagues/{$league->id}")
        ->assertForbidden();
});

it('requires auth', function () {
    $this->getJson('/api/leagues')->assertUnauthorized();
    $this->postJson('/api/leagues')->assertUnauthorized();
});
