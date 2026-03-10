<?php

use App\Enums\ItemSource;
use App\Enums\LeagueMemberRole;
use App\Models\Item;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\UserItem;

beforeEach(function () {
    $this->seed(\Database\Seeders\ItemSeeder::class);
});

it('can claim loot after 8:00 league time', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));

    [$user, $league] = createLeagueWithMember();

    $this->postJson("/api/leagues/{$league->id}/loot/claim")
        ->assertOk()
        ->assertJsonStructure(['user_item', 'message'])
        ->assertJson(['message' => 'Item claimed!']);

    expect(UserItem::where('user_id', $user->id)->where('source', ItemSource::Loot)->exists())->toBeTrue();
});

it('cannot claim before 8:00 league time', function () {
    $this->travelTo(now()->startOfDay()->addHours(5));

    [$user, $league] = createLeagueWithMember();

    $this->postJson("/api/leagues/{$league->id}/loot/claim")
        ->assertUnprocessable()
        ->assertJson(['message' => 'Loot is not available yet.']);
});

it('cannot claim twice same day', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));

    [$user, $league] = createLeagueWithMember();

    $this->postJson("/api/leagues/{$league->id}/loot/claim")
        ->assertOk();

    $this->postJson("/api/leagues/{$league->id}/loot/claim")
        ->assertUnprocessable()
        ->assertJson(['message' => 'Already claimed today.']);
});

it('can claim in two different leagues same day', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));

    [$user, $league1] = createLeagueWithMember();

    $league2 = League::factory()->create(['created_by' => $user->id]);
    LeagueMember::create([
        'league_id' => $league2->id,
        'user_id' => $user->id,
        'role' => LeagueMemberRole::Admin,
    ]);

    $this->postJson("/api/leagues/{$league1->id}/loot/claim")
        ->assertOk();

    $this->postJson("/api/leagues/{$league2->id}/loot/claim")
        ->assertOk();
});

it('returns 422 with is_pro flag when inventory full', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));

    [$user, $league] = createLeagueWithMember();
    $item = Item::first();

    // Fill inventory (5 items for free user)
    for ($i = 0; $i < 5; $i++) {
        UserItem::create([
            'user_id' => $user->id,
            'league_id' => $league->id,
            'item_id' => $item->id,
            'source' => ItemSource::DailyWin,
            'expires_at' => now()->addDays(7),
        ]);
    }

    $this->postJson("/api/leagues/{$league->id}/loot/claim")
        ->assertUnprocessable()
        ->assertJson(['message' => 'Inventory full.', 'is_pro' => false]);
});

it('shows can_claim_loot true when eligible in today response', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));

    [$user, $league] = createLeagueWithMember();

    $this->getJson("/api/leagues/{$league->id}/today")
        ->assertOk()
        ->assertJson(['can_claim_loot' => true]);
});

it('shows can_claim_loot false when already claimed in today response', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));

    [$user, $league] = createLeagueWithMember();

    $this->postJson("/api/leagues/{$league->id}/loot/claim")
        ->assertOk();

    $this->getJson("/api/leagues/{$league->id}/today")
        ->assertOk()
        ->assertJson(['can_claim_loot' => false]);
});

it('non-member cannot claim loot', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));

    actingAsUser();
    $league = League::factory()->create();

    $this->postJson("/api/leagues/{$league->id}/loot/claim")
        ->assertForbidden();
});
