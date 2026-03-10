<?php

it('returns user stats', function () {
    actingAsUser();

    $this->getJson('/api/user/stats')
        ->assertOk()
        ->assertJsonStructure(['total_wins', 'total_losses', 'total_steps']);
});

it('updates profile', function () {
    $user = actingAsUser();

    $this->putJson('/api/user/profile', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ])->assertOk();

    expect($user->fresh())
        ->first_name->toBe('Jane')
        ->last_name->toBe('Doe');
});

it('partial profile update', function () {
    $user = actingAsUser(['first_name' => 'Original', 'last_name' => 'Name']);

    $this->putJson('/api/user/profile', ['first_name' => 'Updated'])
        ->assertOk();

    expect($user->fresh())
        ->first_name->toBe('Updated')
        ->last_name->toBe('Name');
});

it('updates notification settings', function () {
    $user = actingAsUser();
    $settings = ['push_enabled' => true, 'daily_results' => false];

    $this->putJson('/api/user/settings', [
        'notification_settings' => $settings,
    ])->assertOk();

    expect($user->fresh()->notification_settings)->toBe($settings);
});

it('updates onesignal player id', function () {
    $user = actingAsUser();

    $this->putJson('/api/user/settings', [
        'notification_settings' => ['push_enabled' => true],
        'onesignal_player_id' => 'player-123',
    ])->assertOk();

    expect($user->fresh()->onesignal_player_id)->toBe('player-123');
});

it('returns free subscription', function () {
    actingAsUser(['is_pro' => false]);

    $this->getJson('/api/user/subscription')
        ->assertOk()
        ->assertJson(['is_pro' => false]);
});

it('returns pro subscription', function () {
    actingAsUser(['is_pro' => true, 'pro_expires_at' => now()->addYear()]);

    $this->getJson('/api/user/subscription')
        ->assertOk()
        ->assertJson(['is_pro' => true])
        ->assertJsonStructure(['pro_expires_at']);
});

it('all endpoints require auth', function () {
    $this->getJson('/api/user/stats')->assertUnauthorized();
    $this->putJson('/api/user/profile')->assertUnauthorized();
    $this->putJson('/api/user/settings')->assertUnauthorized();
    $this->getJson('/api/user/subscription')->assertUnauthorized();
});
