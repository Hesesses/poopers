<?php

use App\Enums\DraftStatus;
use App\Enums\LeagueMemberRole;
use App\Models\Draft;
use App\Models\DraftPick;
use App\Models\Item;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\ItemSeeder::class);
});

it('lists drafts', function () {
    [$user, $league] = createLeagueWithMember();

    Draft::create([
        'league_id' => $league->id,
        'type' => \App\Enums\DraftType::Weekly,
        'date' => now()->toDateString(),
        'available_items' => Item::pluck('id')->toArray(),
        'pick_order' => [$user->id],
        'status' => DraftStatus::InProgress,
        'expires_at' => now()->addHours(24),
    ]);

    $this->getJson("/api/leagues/{$league->id}/drafts")
        ->assertOk()
        ->assertJsonCount(1);
});

it('shows draft detail', function () {
    [$user, $league] = createLeagueWithMember();

    $draft = Draft::create([
        'league_id' => $league->id,
        'type' => \App\Enums\DraftType::Weekly,
        'date' => now()->toDateString(),
        'available_items' => Item::pluck('id')->toArray(),
        'pick_order' => [$user->id],
        'status' => DraftStatus::InProgress,
        'expires_at' => now()->addHours(24),
    ]);

    $this->getJson("/api/leagues/{$league->id}/drafts/{$draft->id}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'status', 'picks']]);
});

it('picks item in draft', function () {
    [$user, $league] = createLeagueWithMember();
    $item = Item::first();

    $draft = Draft::create([
        'league_id' => $league->id,
        'type' => \App\Enums\DraftType::Weekly,
        'date' => now()->toDateString(),
        'available_items' => [$item->id],
        'pick_order' => [$user->id],
        'current_pick_index' => 0,
        'status' => DraftStatus::InProgress,
        'expires_at' => now()->addHours(24),
    ]);

    $this->postJson("/api/leagues/{$league->id}/drafts/{$draft->id}/pick", [
        'item_id' => $item->id,
    ])->assertOk()->assertJsonStructure(['message', 'pick']);

    expect(DraftPick::where('draft_id', $draft->id)->count())->toBe(1);
    expect(\App\Models\UserItem::where('user_id', $user->id)->where('item_id', $item->id)->exists())->toBeTrue();
});

it('fails if not your turn', function () {
    [$user, $league] = createLeagueWithMember();
    $otherUser = User::factory()->create();
    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $otherUser->id,
        'role' => LeagueMemberRole::Member,
    ]);

    $item = Item::first();

    $draft = Draft::create([
        'league_id' => $league->id,
        'type' => \App\Enums\DraftType::Weekly,
        'date' => now()->toDateString(),
        'available_items' => [$item->id],
        'pick_order' => [$otherUser->id, $user->id],
        'current_pick_index' => 0,
        'status' => DraftStatus::InProgress,
        'expires_at' => now()->addHours(24),
    ]);

    // User tries to pick but it's otherUser's turn
    $this->postJson("/api/leagues/{$league->id}/drafts/{$draft->id}/pick", [
        'item_id' => $item->id,
    ])->assertUnprocessable();
});

it('fails if draft complete', function () {
    [$user, $league] = createLeagueWithMember();
    $item = Item::first();

    $draft = Draft::create([
        'league_id' => $league->id,
        'type' => \App\Enums\DraftType::Weekly,
        'date' => now()->toDateString(),
        'available_items' => [$item->id],
        'pick_order' => [$user->id],
        'current_pick_index' => 0,
        'status' => DraftStatus::Completed,
        'expires_at' => now()->addHours(24),
    ]);

    $this->postJson("/api/leagues/{$league->id}/drafts/{$draft->id}/pick", [
        'item_id' => $item->id,
    ])->assertUnprocessable();
});

it('fails if item already picked', function () {
    [$user, $league] = createLeagueWithMember();
    $items = Item::limit(2)->get();

    $draft = Draft::create([
        'league_id' => $league->id,
        'type' => \App\Enums\DraftType::Weekly,
        'date' => now()->toDateString(),
        'available_items' => $items->pluck('id')->toArray(),
        'pick_order' => [$user->id, $user->id],
        'current_pick_index' => 0,
        'status' => DraftStatus::InProgress,
        'expires_at' => now()->addHours(24),
    ]);

    // Pick first item
    $this->postJson("/api/leagues/{$league->id}/drafts/{$draft->id}/pick", [
        'item_id' => $items[0]->id,
    ])->assertOk();

    // Try to pick same item again
    $this->postJson("/api/leagues/{$league->id}/drafts/{$draft->id}/pick", [
        'item_id' => $items[0]->id,
    ])->assertUnprocessable();
});

it('non-member gets 403', function () {
    actingAsUser();
    $league = League::factory()->create();

    $this->getJson("/api/leagues/{$league->id}/drafts")
        ->assertForbidden();
});

it('requires auth', function () {
    $league = League::factory()->create();

    $this->getJson("/api/leagues/{$league->id}/drafts")
        ->assertUnauthorized();
});
