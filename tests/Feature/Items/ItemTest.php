<?php

use App\Enums\ItemSource;
use App\Enums\ItemType;
use App\Enums\LeagueMemberRole;
use App\Models\Item;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\UserItem;

beforeEach(function () {
    $this->seed(\Database\Seeders\ItemSeeder::class);
});

it('lists user items in league', function () {
    [$user, $league] = createLeagueWithMember();

    $item = Item::first();
    UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $item->id,
        'source' => ItemSource::DailyWin,
        'expires_at' => now()->addDays(7),
    ]);

    $this->getJson("/api/leagues/{$league->id}/items")
        ->assertOk()
        ->assertJsonCount(1);
});

it('only shows unused non-expired items', function () {
    [$user, $league] = createLeagueWithMember();
    $item = Item::first();

    // Used item
    UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $item->id,
        'source' => ItemSource::DailyWin,
        'expires_at' => now()->addDays(7),
        'used_at' => now(),
    ]);

    // Expired item
    UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $item->id,
        'source' => ItemSource::DailyWin,
        'expires_at' => now()->subDay(),
    ]);

    $this->getJson("/api/leagues/{$league->id}/items")
        ->assertOk()
        ->assertJsonCount(0);
});

it('uses offensive item on target', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));

    [$user, $league] = createLeagueWithMember();
    $target = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $target->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $offensiveItem = Item::where('type', ItemType::Offensive)->first();
    $userItem = UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $offensiveItem->id,
        'source' => ItemSource::DailyWin,
        'expires_at' => now()->addDays(7),
    ]);

    $this->postJson("/api/leagues/{$league->id}/items/{$userItem->id}/use", [
        'target_user_id' => $target->id,
    ])->assertOk()->assertJsonStructure(['message', 'effect_status']);

    expect($userItem->fresh()->used_at)->not->toBeNull();
});

it('cannot use item twice', function () {
    [$user, $league] = createLeagueWithMember();
    $target = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $target->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $item = Item::where('type', ItemType::Strategic)->first();
    $userItem = UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $item->id,
        'source' => ItemSource::DailyWin,
        'expires_at' => now()->addDays(7),
        'used_at' => now(),
        'used_on_user_id' => $target->id,
    ]);

    $this->postJson("/api/leagues/{$league->id}/items/{$userItem->id}/use", [
        'target_user_id' => $target->id,
    ])->assertUnprocessable();
});

it('cannot use expired item', function () {
    [$user, $league] = createLeagueWithMember();
    $target = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $target->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $item = Item::where('type', ItemType::Strategic)->first();
    $userItem = UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $item->id,
        'source' => ItemSource::DailyWin,
        'expires_at' => now()->subDay(),
    ]);

    $this->postJson("/api/leagues/{$league->id}/items/{$userItem->id}/use", [
        'target_user_id' => $target->id,
    ])->assertUnprocessable();
});

it('one item per day limit', function () {
    [$user, $league] = createLeagueWithMember();
    $target = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $target->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $items = Item::where('type', ItemType::Strategic)->limit(2)->get();

    // Use first item
    $userItem1 = UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $items[0]->id,
        'source' => ItemSource::DailyWin,
        'expires_at' => now()->addDays(7),
    ]);
    $this->postJson("/api/leagues/{$league->id}/items/{$userItem1->id}/use", [
        'target_user_id' => $target->id,
    ])->assertOk();

    // Try second item same day
    $userItem2 = UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $items[1]->id,
        'source' => ItemSource::DailyWin,
        'expires_at' => now()->addDays(7),
    ]);
    $this->postJson("/api/leagues/{$league->id}/items/{$userItem2->id}/use", [
        'target_user_id' => $target->id,
    ])->assertUnprocessable();
});

it('non-member gets 403', function () {
    actingAsUser();
    $league = League::factory()->create();

    $this->getJson("/api/leagues/{$league->id}/items")
        ->assertForbidden();
});

it('requires auth', function () {
    $league = League::factory()->create();

    $this->getJson("/api/leagues/{$league->id}/items")
        ->assertUnauthorized();
});

it('requires target_user_id', function () {
    [$user, $league] = createLeagueWithMember();
    $item = Item::first();
    $userItem = UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $item->id,
        'source' => ItemSource::DailyWin,
        'expires_at' => now()->addDays(7),
    ]);

    $this->postJson("/api/leagues/{$league->id}/items/{$userItem->id}/use", [])
        ->assertUnprocessable();
});
