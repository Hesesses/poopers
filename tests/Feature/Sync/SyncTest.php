<?php

use App\Models\DailySteps;

it('syncs steps for today', function () {
    actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 5000])
        ->assertOk()
        ->assertJsonStructure(['steps', 'modified_steps', 'date']);
});

it('creates daily steps record', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 5000]);

    expect(DailySteps::where('user_id', $user->id)->exists())->toBeTrue();
});

it('updates existing record', function () {
    $user = actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => 3000, 'date' => '2026-03-01']);

    $this->postJson('/api/sync/steps', ['steps' => 7000, 'date' => '2026-03-02'])
        ->assertOk()
        ->assertJson(['steps' => 7000, 'date' => '2026-03-02']);

    expect(DailySteps::where('user_id', $user->id)->count())->toBe(2);
});

it('syncs for specific date', function () {
    actingAsUser();

    $response = $this->postJson('/api/sync/steps', [
        'steps' => 4000,
        'date' => '2026-03-01',
    ]);

    $response->assertOk()->assertJson(['date' => '2026-03-01']);
});

it('validates steps required', function () {
    actingAsUser();

    $this->postJson('/api/sync/steps', [])
        ->assertUnprocessable();
});

it('validates steps non-negative', function () {
    actingAsUser();

    $this->postJson('/api/sync/steps', ['steps' => -100])
        ->assertUnprocessable();
});

it('requires auth', function () {
    $this->postJson('/api/sync/steps', ['steps' => 5000])
        ->assertUnauthorized();
});
