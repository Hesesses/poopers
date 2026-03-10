<?php

use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
});

it('sends magic link for valid email', function () {
    $response = $this->postJson('/api/auth/magic-link', ['email' => 'test@example.com']);

    $response->assertOk()->assertJsonStructure(['message']);
    expect(MagicLink::where('email', 'test@example.com')->exists())->toBeTrue();
});

it('fails without email', function () {
    $this->postJson('/api/auth/magic-link', [])
        ->assertUnprocessable();
});

it('fails with invalid email', function () {
    $this->postJson('/api/auth/magic-link', ['email' => 'not-an-email'])
        ->assertUnprocessable();
});

it('verifies valid token and returns user and token', function () {
    $magicLink = MagicLink::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'code' => '123456',
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/auth/verify', ['token' => $magicLink->token]);

    $response->assertOk()->assertJsonStructure(['user', 'token']);
});

it('creates new user on first verify', function () {
    $magicLink = MagicLink::create([
        'email' => 'newuser@example.com',
        'token' => Str::random(64),
        'code' => '123456',
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->postJson('/api/auth/verify', ['token' => $magicLink->token]);

    expect(User::where('email', 'newuser@example.com')->count())->toBe(1);
});

it('returns existing user on repeat verify', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $magicLink = MagicLink::create([
        'email' => 'existing@example.com',
        'token' => Str::random(64),
        'code' => '123456',
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->postJson('/api/auth/verify', ['token' => $magicLink->token]);

    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

it('fails with invalid token', function () {
    $this->postJson('/api/auth/verify', ['token' => Str::random(64)])
        ->assertUnauthorized();
});

it('fails with expired token', function () {
    $magicLink = MagicLink::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'code' => '123456',
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/auth/verify', ['token' => $magicLink->token])
        ->assertUnauthorized();
});

it('fails with used token', function () {
    $magicLink = MagicLink::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'code' => '123456',
        'expires_at' => now()->addMinutes(15),
        'used_at' => now(),
    ]);

    $this->postJson('/api/auth/verify', ['token' => $magicLink->token])
        ->assertUnauthorized();
});

it('verifies valid 6-digit code and returns user and token', function () {
    $magicLink = MagicLink::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'code' => '987654',
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/auth/verify', ['token' => '987654']);

    $response->assertOk()->assertJsonStructure(['user', 'token']);
});

it('creates new user via 6-digit code on first login', function () {
    $magicLink = MagicLink::create([
        'email' => 'codeuser@example.com',
        'token' => Str::random(64),
        'code' => '111222',
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->postJson('/api/auth/verify', ['token' => '111222']);

    expect(User::where('email', 'codeuser@example.com')->count())->toBe(1);
});

it('fails with invalid 6-digit code', function () {
    $this->postJson('/api/auth/verify', ['token' => '000000'])
        ->assertUnauthorized();
});

it('fails with expired 6-digit code', function () {
    MagicLink::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'code' => '333444',
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/auth/verify', ['token' => '333444'])
        ->assertUnauthorized();
});

it('fails with used 6-digit code', function () {
    MagicLink::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'code' => '555666',
        'expires_at' => now()->addMinutes(15),
        'used_at' => now(),
    ]);

    $this->postJson('/api/auth/verify', ['token' => '555666'])
        ->assertUnauthorized();
});

it('generates a 6-digit code when sending magic link', function () {
    $this->postJson('/api/auth/magic-link', ['email' => 'test@example.com']);

    $magicLink = MagicLink::where('email', 'test@example.com')->first();

    expect($magicLink->code)->toMatch('/^\d{6}$/');
});

it('returns authenticated user', function () {
    $user = actingAsUser(['first_name' => 'John', 'email' => 'john@example.com']);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonFragment(['id' => $user->id, 'email' => 'john@example.com', 'first_name' => 'John']);
});

it('me requires auth', function () {
    $this->getJson('/api/auth/me')
        ->assertUnauthorized();
});

it('logout invalidates token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('delete account soft deletes user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/auth/account')
        ->assertOk();

    expect($user->fresh()->trashed())->toBeTrue();
});
