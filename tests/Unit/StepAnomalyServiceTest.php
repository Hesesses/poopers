<?php

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\StepAnomaly;
use App\Models\User;
use App\Services\StepAnomalyService;

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in(__DIR__);

it('creates an anomaly record with correct attributes', function () {
    $user = User::factory()->create();
    $service = new StepAnomalyService;

    $service->flag($user, '2026-03-01', AnomalyType::MaxExceeded, AnomalySeverity::High, ['steps' => 150_000]);

    $anomaly = StepAnomaly::where('user_id', $user->id)->first();

    expect($anomaly)->not->toBeNull();
    expect($anomaly->anomaly_type)->toBe(AnomalyType::MaxExceeded);
    expect($anomaly->severity)->toBe(AnomalySeverity::High);
    expect($anomaly->details)->toBe(['steps' => 150_000]);
    expect($anomaly->date->toDateString())->toBe('2026-03-01');
    expect($anomaly->reviewed)->toBeFalse();
});

it('catches exceptions silently without blocking', function () {
    $user = User::factory()->create();
    $service = new StepAnomalyService;

    // Force an error by using an invalid date format that will fail at DB level
    // The service should catch and log, not throw
    $service->flag($user, 'invalid-date', AnomalyType::MaxExceeded, AnomalySeverity::High, []);

    // If we reach here without exception, the test passes
    expect(true)->toBeTrue();
});

it('creates multiple anomalies for the same user and date', function () {
    $user = User::factory()->create();
    $service = new StepAnomalyService;

    $service->flag($user, '2026-03-01', AnomalyType::MaxExceeded, AnomalySeverity::High, ['steps' => 150_000]);
    $service->flag($user, '2026-03-01', AnomalyType::RoundNumber, AnomalySeverity::Low, ['steps' => 150_000]);

    expect(StepAnomaly::where('user_id', $user->id)->count())->toBe(2);
});
