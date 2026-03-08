<?php

use App\Models\League;

it('returns month standings', function () {
    [$user, $league] = createLeagueWithMember();

    $this->getJson("/api/leagues/{$league->id}/standings/month")
        ->assertOk()
        ->assertJsonStructure(['standings']);
});

it('returns week standings', function () {
    [$user, $league] = createLeagueWithMember();

    $this->getJson("/api/leagues/{$league->id}/standings/week")
        ->assertOk()
        ->assertJsonStructure(['standings']);
});

it('returns yesterday results', function () {
    [$user, $league] = createLeagueWithMember();

    $this->getJson("/api/leagues/{$league->id}/standings/yesterday")
        ->assertOk()
        ->assertJsonStructure(['results']);
});

it('returns today data', function () {
    [$user, $league] = createLeagueWithMember();

    $this->getJson("/api/leagues/{$league->id}/today")
        ->assertOk()
        ->assertJsonStructure(['standings', 'visibility']);
});

it('non-member gets 403', function () {
    actingAsUser();
    $league = League::factory()->create();

    $this->getJson("/api/leagues/{$league->id}/standings/month")
        ->assertForbidden();
});

it('requires auth', function () {
    $league = League::factory()->create();

    $this->getJson("/api/leagues/{$league->id}/standings/month")
        ->assertUnauthorized();
});
