<?php

use App\Enums\AnomalyType;
use App\Models\StepAnomaly;

beforeEach(function () {
    config(['anticheat.layers.velocity' => true]);
});

it('flags hourly steps exceeding max', function () {
    $user = actingAsUser();

    $hourly = array_fill(0, 24, 0);
    $hourly[10] = 20_000;

    $this->postJson('/api/sync/steps', [
        'steps' => 20_000,
        'hourly_steps' => $hourly,
    ])->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::VelocityExceeded)->exists())->toBeTrue();
});

it('flags hourly total mismatch', function () {
    $user = actingAsUser();

    $hourly = array_fill(0, 24, 0);
    $hourly[8] = 2_000;
    $hourly[12] = 3_000;

    $this->postJson('/api/sync/steps', [
        'steps' => 50_000,
        'hourly_steps' => $hourly,
    ])->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::HourlyMismatch)->exists())->toBeTrue();
});

it('flags suspicious night activity', function () {
    $user = actingAsUser();

    $hourly = array_fill(0, 24, 0);
    $hourly[0] = 2_000;
    $hourly[1] = 2_000;
    $hourly[2] = 2_000;

    $this->postJson('/api/sync/steps', [
        'steps' => 6_000,
        'hourly_steps' => $hourly,
    ])->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::SuspiciousNight)->exists())->toBeTrue();
});

it('does not flag when hourly data is within limits', function () {
    $user = actingAsUser();

    $hourly = array_fill(0, 24, 0);
    $hourly[8] = 2_000;
    $hourly[12] = 3_000;
    $hourly[17] = 3_000;

    $this->postJson('/api/sync/steps', [
        'steps' => 8_000,
        'hourly_steps' => $hourly,
    ])->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)
        ->whereIn('anomaly_type', [AnomalyType::VelocityExceeded, AnomalyType::HourlyMismatch, AnomalyType::SuspiciousNight])
        ->count())->toBe(0);
});

it('does not check velocity when hourly_steps not provided', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 8_000])
        ->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)
        ->whereIn('anomaly_type', [AnomalyType::VelocityExceeded, AnomalyType::HourlyMismatch])
        ->count())->toBe(0);
});

it('does not check velocity when layer is disabled', function () {
    config(['anticheat.layers.velocity' => false]);

    $user = actingAsUser();

    $hourly = array_fill(0, 24, 0);
    $hourly[10] = 20_000;

    $this->postJson('/api/sync/steps', [
        'steps' => 20_000,
        'hourly_steps' => $hourly,
    ])->assertSuccessful();

    expect(StepAnomaly::where('user_id', $user->id)->where('anomaly_type', AnomalyType::VelocityExceeded)->count())->toBe(0);
});
