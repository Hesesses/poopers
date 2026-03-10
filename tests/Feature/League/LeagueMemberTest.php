<?php

use App\Enums\ItemEffectStatus;
use App\Enums\ItemSource;
use App\Enums\ItemType;
use App\Enums\LeagueMemberRole;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\UserItem;

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

it('new member receives auto-activated all seeing eye on join', function () {
    $this->seed(\Database\Seeders\ItemSeeder::class);

    [$admin, $league] = createLeagueWithMember();
    $joiner = actingAsUser();

    $this->postJson("/api/leagues/{$league->id}/join", [
        'invite_code' => $league->invite_code,
    ])->assertOk();

    $userItem = UserItem::query()
        ->where('user_id', $joiner->id)
        ->where('league_id', $league->id)
        ->whereHas('item', fn ($q) => $q->where('slug', 'all_seeing_eye'))
        ->first();

    expect($userItem)->not->toBeNull();
    expect($userItem->used_at)->not->toBeNull();
    expect($userItem->used_on_user_id)->toBe($joiner->id);

    $effect = ItemEffect::query()
        ->where('user_item_id', $userItem->id)
        ->where('target_user_id', $joiner->id)
        ->where('league_id', $league->id)
        ->where('status', ItemEffectStatus::Applied)
        ->first();

    expect($effect)->not->toBeNull();

    // Verify 5 additional welcome items (2 offensive, 2 defensive, 1 strategic)
    $welcomeItems = UserItem::query()
        ->where('user_id', $joiner->id)
        ->where('league_id', $league->id)
        ->where('source', ItemSource::Welcome)
        ->whereDoesntHave('item', fn ($q) => $q->where('slug', 'all_seeing_eye'))
        ->get();

    expect($welcomeItems)->toHaveCount(5);

    $typeCounts = $welcomeItems->groupBy(fn ($ui) => $ui->item->type->value)->map->count();
    expect($typeCounts[ItemType::Offensive->value])->toBe(2);
    expect($typeCounts[ItemType::Defensive->value])->toBe(2);
    expect($typeCounts[ItemType::Strategic->value])->toBe(1);
});

it('rejoining league does not grant welcome items again', function () {
    $this->seed(\Database\Seeders\ItemSeeder::class);

    [$admin, $league] = createLeagueWithMember();
    $joiner = actingAsUser();

    // First join
    $this->postJson("/api/leagues/{$league->id}/join", [
        'invite_code' => $league->invite_code,
    ])->assertOk();

    $firstJoinCount = UserItem::query()
        ->where('user_id', $joiner->id)
        ->where('league_id', $league->id)
        ->where('source', ItemSource::Welcome)
        ->count();

    expect($firstJoinCount)->toBe(6); // 1 All Seeing Eye + 5 starter pack

    // Leave
    $this->postJson("/api/leagues/{$league->id}/leave")->assertOk();

    // Rejoin
    $this->postJson("/api/leagues/{$league->id}/join", [
        'invite_code' => $league->invite_code,
    ])->assertOk();

    $totalWelcomeItems = UserItem::query()
        ->where('user_id', $joiner->id)
        ->where('league_id', $league->id)
        ->where('source', ItemSource::Welcome)
        ->count();

    expect($totalWelcomeItems)->toBe(6); // Still 6, not 12
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
