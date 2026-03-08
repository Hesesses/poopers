<?php

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\StepAnomaly;

it('flags steps exceeding max daily threshold', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 150_000])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::MaxExceeded)->exists())->toBeTrue();
    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::MaxExceeded)->first()->severity)->toBe(AnomalySeverity::High);
});

it('flags steps decreased from previous sync', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 10_000, 'date' => '2026-03-01']);

    $this->postJson('/api/sync/steps', ['steps' => 5_000, 'date' => '2026-03-01'])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::StepsDecreased)->exists())->toBeTrue();
});

it('flags large jump between syncs', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 1_000, 'date' => '2026-03-01']);

    $this->postJson('/api/sync/steps', ['steps' => 50_000, 'date' => '2026-03-01'])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::LargeJump)->exists())->toBeTrue();
});

it('flags round numbers above threshold', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 10_000])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::RoundNumber)->exists())->toBeTrue();
});

it('does not flag round numbers below minimum', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 3_000])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::RoundNumber)->exists())->toBeFalse();
});

it('does not flag normal step counts', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 8_423])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->count())->toBe(0);
});

it('does not flag when increasing steps normally', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 5_000, 'date' => '2026-03-01']);
    StepAnomaly::where('user_id', $user->id)->delete();

    $this->postJson('/api/sync/steps', ['steps' => 8_500, 'date' => '2026-03-01'])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->count())->toBe(0);
});

it('does not flag when heuristics layer is disabled', function () {
    config(['anticheat.layers.heuristics' => false]);

    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 150_000])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->count())->toBe(0);
});
