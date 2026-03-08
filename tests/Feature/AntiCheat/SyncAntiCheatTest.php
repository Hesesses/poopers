<?php

use App\Models\DailySteps;
use App\Models\StepAnomaly;

it('still saves data when anomalies are detected', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 150_000])
        ->assertSuccessful()
        ->assertJson(['steps' => 150_000]);

    expect(DailySteps::where('user_id', $user->id)->first()->steps)->toBe(150_000);
    expect(StepAnomaly::where('user_id', $user->id)->exists())->toBeTrue();
});

it('creates multiple anomaly records for multiple violations', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 1_000]);
    StepAnomaly::where('user_id', $user->id)->delete();

    // 150K triggers: max_exceeded + large_jump + round_number
    $this->postJson('/api/sync/steps', ['steps' => 150_000])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->count())->toBeGreaterThanOrEqual(2);
});

it('stores hourly_steps on daily_steps record', function () {
    config(['anticheat.layers.velocity' => true]);

    $user = actingAsUser();

    $hourly = array_fill(0, 24, 0);
    $hourly[8] = 3_000;
    $hourly[12] = 5_000;

    $this->postJson('/api/sync/steps', [
        'steps' => 8_000,
        'hourly_steps' => $hourly,
    ])->assertSuccessful();

    $record = DailySteps::where('user_id', $user->id)->first();
    expect($record->hourly_steps)->toBeArray();
    expect($record->hourly_steps[8])->toBe(3_000);
    expect($record->hourly_steps[12])->toBe(5_000);
});

it('validates hourly_steps must have 24 elements', function () {
    actingAsUser();

    $this->postJson('/api/sync/steps', [
        'steps' => 5_000,
        'hourly_steps' => [100, 200, 300],
    ])->assertUnprocessable();
});

it('validates hourly_steps values must be non-negative integers', function () {
    actingAsUser();

    $hourly = array_fill(0, 24, 0);
    $hourly[5] = -100;

    $this->postJson('/api/sync/steps', [
        'steps' => 5_000,
        'hourly_steps' => $hourly,
    ])->assertUnprocessable();
});

it('rate limits sync requests', function () {
    actingAsUser();

    for ($i = 0; $i < 30; $i++) {
        $this->postJson('/api/sync/steps', ['steps' => 1_000]);
    }

    $this->postJson('/api/sync/steps', ['steps' => 1_000])
        ->assertStatus(429);
});

it('does not run anti-cheat when master toggle is disabled', function () {
    config(['anticheat.enabled' => false]);

    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 150_000])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->count())->toBe(0);
});
