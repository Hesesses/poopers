<?php

use App\Models\Notification;

it('lists notifications', function () {
    $user = actingAsUser();

    Notification::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Test',
        'body' => 'Test body',
    ]);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(1);
});

it('marks notifications as read', function () {
    $user = actingAsUser();

    $notification = Notification::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Test',
        'body' => 'Test body',
        'is_read' => false,
    ]);

    $this->postJson('/api/notifications/read', [
        'notification_ids' => [$notification->id],
    ])->assertOk();

    expect($notification->fresh()->is_read)->toBeTrue();
});

it('marks all as read', function () {
    $user = actingAsUser();

    Notification::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Test 1',
        'body' => 'Body 1',
        'is_read' => false,
    ]);
    Notification::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Test 2',
        'body' => 'Body 2',
        'is_read' => false,
    ]);

    $this->postJson('/api/notifications/read-all')
        ->assertOk();

    expect(Notification::where('user_id', $user->id)->where('is_read', false)->count())->toBe(0);
});

it('requires auth', function () {
    $this->getJson('/api/notifications')
        ->assertUnauthorized();
});
