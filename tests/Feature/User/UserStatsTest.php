<?php

use App\Enums\ItemEffectStatus;
use App\Enums\StreakType;
use App\Models\DailySteps;
use App\Models\Item;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\LeagueMember;
use App\Models\Streak;
use App\Models\UserItem;

it('requires authentication', function () {
    $this->getJson('/api/user/stats')
        ->assertUnauthorized();
});

it('returns all 13 stat fields as integers with no data wrapper', function () {
    $user = actingAsUser();

    $response = $this->getJson('/api/user/stats');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'total_wins',
            'total_losses',
            'total_steps',
            'leagues_count',
            'winning_streak_best',
            'not_losing_streak_best',
            'days_competed',
            'current_win_streak',
            'current_not_losing_streak',
            'current_pooper_streak',
            'most_steps_in_day',
            'items_used',
            'attacks_sent',
            'attacks_blocked',
        ]);

    // Ensure no data wrapper
    expect($response->json())->not->toHaveKey('data');

    // All values should be integers
    foreach ($response->json() as $key => $value) {
        expect($value)->toBeInt("Expected {$key} to be an integer");
    }
});

it('returns correct stats for a user with activity', function () {
    $user = actingAsUser(['created_at' => now()->subDays(5)]);
    $league = League::factory()->create(['created_by' => $user->id]);

    LeagueMember::create([
        'league_id' => $league->id,
        'user_id' => $user->id,
        'role' => \App\Enums\LeagueMemberRole::Admin,
        'created_at' => now()->subDays(5),
    ]);

    // Create day results: 2 wins, 1 loss, across 2 distinct dates
    LeagueDayResult::create([
        'league_id' => $league->id,
        'user_id' => $user->id,
        'date' => now()->subDays(2),
        'steps' => 8000,
        'modified_steps' => 8000,
        'position' => 1,
        'is_winner' => true,
        'is_last' => false,
    ]);

    LeagueDayResult::create([
        'league_id' => $league->id,
        'user_id' => $user->id,
        'date' => now()->subDay(),
        'steps' => 10000,
        'modified_steps' => 10000,
        'position' => 1,
        'is_winner' => true,
        'is_last' => false,
    ]);

    LeagueDayResult::create([
        'league_id' => $league->id,
        'user_id' => $user->id,
        'date' => now(),
        'steps' => 3000,
        'modified_steps' => 3000,
        'position' => 3,
        'is_winner' => false,
        'is_last' => true,
    ]);

    // Daily steps
    DailySteps::create(['user_id' => $user->id, 'date' => now()->subDay(), 'steps' => 12000, 'last_synced_at' => now()]);
    DailySteps::create(['user_id' => $user->id, 'date' => now(), 'steps' => 5000, 'last_synced_at' => now()]);

    // Streaks
    Streak::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'type' => StreakType::Winning,
        'current_count' => 2,
        'best_count' => 3,
    ]);

    Streak::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'type' => StreakType::NotLosing,
        'current_count' => 5,
        'best_count' => 7,
    ]);

    Streak::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'type' => StreakType::Pooper,
        'current_count' => 1,
        'best_count' => 2,
    ]);

    // Create an item for FK constraint
    $item = Item::create([
        'slug' => 'test-item',
        'name' => 'Test Item',
        'description' => 'A test item',
        'type' => \App\Enums\ItemType::Offensive,
        'rarity' => \App\Enums\ItemRarity::Common,
        'effect' => [],
        'icon' => 'test',
    ]);

    // Used item
    $userItem = UserItem::create([
        'user_id' => $user->id,
        'league_id' => $league->id,
        'item_id' => $item->id,
        'source' => \App\Enums\ItemSource::DailyWin,
        'expires_at' => now()->addWeek(),
        'used_at' => now(),
    ]);

    // Attack sent by user
    ItemEffect::create([
        'user_item_id' => $userItem->id,
        'target_user_id' => $user->id,
        'league_id' => $league->id,
        'date' => now(),
        'status' => ItemEffectStatus::Applied,
    ]);

    // Attack blocked against user
    $otherUser = \App\Models\User::factory()->create();
    $otherItem = UserItem::create([
        'user_id' => $otherUser->id,
        'league_id' => $league->id,
        'item_id' => $item->id,
        'source' => \App\Enums\ItemSource::DailyWin,
        'expires_at' => now()->addWeek(),
        'used_at' => now(),
    ]);

    ItemEffect::create([
        'user_item_id' => $otherItem->id,
        'target_user_id' => $user->id,
        'league_id' => $league->id,
        'date' => now(),
        'status' => ItemEffectStatus::Blocked,
    ]);

    $response = $this->getJson('/api/user/stats');

    $response->assertSuccessful();

    // Streaks are computed from LeagueDayResult data:
    // Day 1: win=true, last=false → win:1, not_losing:1
    // Day 2: win=true, last=false → win:2, not_losing:2
    // Day 3: win=false, last=true → win:0, not_losing:0, pooper:1
    expect($response->json())
        ->total_wins->toBe(2)
        ->total_losses->toBe(1)
        ->total_steps->toBe(17000)
        ->leagues_count->toBe(1)
        ->winning_streak_best->toBe(2)
        ->not_losing_streak_best->toBe(2)
        ->days_competed->toBe(3)
        ->current_win_streak->toBe(0)
        ->current_not_losing_streak->toBe(0)
        ->current_pooper_streak->toBe(1)
        ->most_steps_in_day->toBe(12000)
        ->items_used->toBe(1)
        ->attacks_sent->toBe(1)
        ->attacks_blocked->toBe(1);
});
