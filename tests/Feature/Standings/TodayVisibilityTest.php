<?php

use App\Enums\ItemEffectStatus;
use App\Enums\ItemEffectType;
use App\Enums\LeagueMemberRole;
use App\Models\DailySteps;
use App\Models\ItemEffect;
use App\Models\LeagueMember;
use App\Models\LeagueNoonSnapshot;
use App\Models\User;
use App\Models\UserItem;
use Carbon\Carbon;

function createLeagueWithMembers(int $memberCount = 3): array
{
    $users = [];
    $user = actingAsUser();
    $league = \App\Models\League::factory()->create(['created_by' => $user->id, 'timezone' => 'UTC']);
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => LeagueMemberRole::Admin]);
    $users[] = $user;

    for ($i = 1; $i < $memberCount; $i++) {
        $member = User::factory()->create();
        LeagueMember::create(['league_id' => $league->id, 'user_id' => $member->id, 'role' => LeagueMemberRole::Member]);
        $users[] = $member;
    }

    return [$users, $league];
}

function createStepsForUsers(array $users, array $stepValues): void
{
    $today = today();
    foreach ($users as $i => $user) {
        $steps = $stepValues[$i] ?? 0;
        DailySteps::create([
            'user_id' => $user->id,
            'date' => $today,
            'steps' => $steps,
            'modified_steps' => $steps,
            'last_synced_at' => now(),
        ]);
    }
}

function createItemWithEffect(array $users, \App\Models\League $league, ItemEffectType $type, string $name, int $attackerIndex, int $targetIndex): void
{
    $item = \App\Models\Item::create([
        'slug' => \Illuminate\Support\Str::slug($name),
        'name' => $name,
        'description' => 'test',
        'type' => \App\Enums\ItemType::Offensive,
        'rarity' => \App\Enums\ItemRarity::Common,
        'effect' => ['type' => $type->value],
        'icon' => 'test',
    ]);
    $userItem = UserItem::create([
        'user_id' => $users[$attackerIndex]->id,
        'league_id' => $league->id,
        'item_id' => $item->id,
        'source' => \App\Enums\ItemSource::DailyWin,
        'expires_at' => now()->addWeek(),
        'used_at' => now(),
    ]);
    ItemEffect::create([
        'user_item_id' => $userItem->id,
        'target_user_id' => $users[$targetIndex]->id,
        'league_id' => $league->id,
        'date' => today(),
        'status' => ItemEffectStatus::Applied,
    ]);
}

it('returns hidden phase before noon with own steps visible', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00', 'UTC'));

    [$users, $league] = createLeagueWithMembers();
    createStepsForUsers($users, [5000, 8000, 3000]);

    $response = $this->getJson("/api/leagues/{$league->id}/today");

    $response->assertSuccessful();
    expect($response->json('visibility'))->toBe('hidden');

    $standings = $response->json('standings');
    // Current user should be first
    expect($standings[0]['is_self'])->toBeTrue();
    expect($standings[0]['own_steps'])->toBe(5000);
    expect($standings[0]['show_steps'])->toBeTrue();

    // Other members should have hidden steps
    expect($standings[1]['show_steps'])->toBeFalse();
    expect($standings[1]['steps'])->toBeNull();
    expect($standings[1]['show_positions'])->toBeFalse();
    expect($standings[2]['show_steps'])->toBeFalse();
});

it('returns noon_reveal phase with snapshot data between 12 and 18', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 14:00:00', 'UTC'));

    [$users, $league] = createLeagueWithMembers();
    $today = today();

    // Create snapshots (as if job ran at noon)
    foreach ($users as $i => $user) {
        LeagueNoonSnapshot::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'date' => $today,
            'steps' => [5000, 8000, 3000][$i],
            'modified_steps' => [5000, 8000, 3000][$i],
            'position' => [2, 1, 3][$i],
        ]);
    }

    // Create different live steps to verify snapshot is used
    createStepsForUsers($users, [7000, 9000, 4000]);

    $response = $this->getJson("/api/leagues/{$league->id}/today");

    $response->assertSuccessful();
    expect($response->json('visibility'))->toBe('noon_reveal');

    $standings = $response->json('standings');
    // Should be sorted by snapshot position — user with 8000 steps first
    expect($standings[0]['steps'])->toBe(8000);
    expect($standings[0]['position'])->toBe(1);
    expect($standings[0]['show_steps'])->toBeTrue();
    expect($standings[0]['show_positions'])->toBeTrue();
});

it('falls back to hidden when no snapshot exists at noon_reveal', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 13:00:00', 'UTC'));

    [$users, $league] = createLeagueWithMembers();
    createStepsForUsers($users, [5000, 8000, 3000]);

    $response = $this->getJson("/api/leagues/{$league->id}/today");

    $response->assertSuccessful();
    expect($response->json('visibility'))->toBe('hidden');
});

it('returns evening phase after 18 with live positions and hidden steps', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 20:00:00', 'UTC'));

    [$users, $league] = createLeagueWithMembers();
    createStepsForUsers($users, [5000, 8000, 3000]);

    $response = $this->getJson("/api/leagues/{$league->id}/today");

    $response->assertSuccessful();
    expect($response->json('visibility'))->toBe('evening');

    $standings = $response->json('standings');
    // Sorted by live steps desc: 8000, 5000, 3000
    expect($standings[0]['position'])->toBe(1);
    expect($standings[0]['show_positions'])->toBeTrue();
    expect($standings[0]['show_steps'])->toBeFalse();
    expect($standings[0]['steps'])->toBeNull();
});

it('expose_steps effect shows live steps in any phase', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00', 'UTC'));

    [$users, $league] = createLeagueWithMembers(2);
    createStepsForUsers($users, [5000, 8000]);

    createItemWithEffect($users, $league, ItemEffectType::ExposeSteps, 'Toilet Paper Trail', 0, 1);

    $response = $this->getJson("/api/leagues/{$league->id}/today");

    $response->assertSuccessful();

    $standings = $response->json('standings');
    $exposedUser = collect($standings)->firstWhere('is_self', false);
    expect($exposedUser['show_steps'])->toBeTrue();
    expect($exposedUser['steps'])->toBe(8000);
});

it('hide_ranking effect hides position during noon_reveal', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 14:00:00', 'UTC'));

    [$users, $league] = createLeagueWithMembers(2);
    $today = today();

    // Create snapshots
    LeagueNoonSnapshot::create([
        'league_id' => $league->id,
        'user_id' => $users[0]->id,
        'date' => $today,
        'steps' => 5000,
        'modified_steps' => 5000,
        'position' => 2,
    ]);
    LeagueNoonSnapshot::create([
        'league_id' => $league->id,
        'user_id' => $users[1]->id,
        'date' => $today,
        'steps' => 8000,
        'modified_steps' => 8000,
        'position' => 1,
    ]);

    createStepsForUsers($users, [5000, 8000]);

    createItemWithEffect($users, $league, ItemEffectType::HideRanking, 'The Brown Out', 1, 0);

    $response = $this->getJson("/api/leagues/{$league->id}/today");

    $response->assertSuccessful();
    $standings = $response->json('standings');
    $self = collect($standings)->firstWhere('is_self', true);
    expect($self['show_positions'])->toBeFalse();
});

it('TakeNoonSnapshots job creates snapshots at noon', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 12:30:00', 'UTC'));

    [$users, $league] = createLeagueWithMembers();
    createStepsForUsers($users, [5000, 8000, 3000]);

    (new \App\Jobs\TakeNoonSnapshots)->handle();

    $snapshots = LeagueNoonSnapshot::where('league_id', $league->id)
        ->where('date', today())
        ->orderBy('position')
        ->get();

    expect($snapshots)->toHaveCount(3);
    expect($snapshots[0]->modified_steps)->toBe(8000);
    expect($snapshots[0]->position)->toBe(1);
    expect($snapshots[1]->modified_steps)->toBe(5000);
    expect($snapshots[1]->position)->toBe(2);
    expect($snapshots[2]->modified_steps)->toBe(3000);
    expect($snapshots[2]->position)->toBe(3);
});

it('TakeNoonSnapshots job is idempotent', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 12:30:00', 'UTC'));

    [$users, $league] = createLeagueWithMembers(2);
    createStepsForUsers($users, [5000, 8000]);

    (new \App\Jobs\TakeNoonSnapshots)->handle();
    (new \App\Jobs\TakeNoonSnapshots)->handle();

    $count = LeagueNoonSnapshot::where('league_id', $league->id)
        ->where('date', today())
        ->count();

    expect($count)->toBe(2);
});
