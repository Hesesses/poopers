<?php

use App\Http\Controllers\Api\AttestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DraftController;
use App\Http\Controllers\Api\InviteCodeController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\LeagueController;
use App\Http\Controllers\Api\LeagueMemberController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\StandingsController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Auth (public)
Route::prefix('auth')->group(function () {
    Route::post('/magic-link', [AuthController::class, 'sendMagicLink']);
    Route::post('/verify', [AuthController::class, 'verify']);

    if (app()->environment('local')) {
        Route::post('/dev-login', [AuthController::class, 'devLogin']);
    }
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::delete('/auth/account', [AuthController::class, 'deleteAccount']);

    // User
    Route::get('/user/stats', [UserController::class, 'stats']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::put('/user/settings', [UserController::class, 'updateSettings']);
    Route::get('/user/subscription', [UserController::class, 'subscription']);

    // Leagues
    Route::get('/leagues', [LeagueController::class, 'index']);
    Route::post('/leagues', [LeagueController::class, 'store']);
    Route::get('/leagues/{league}', [LeagueController::class, 'show']);
    Route::put('/leagues/{league}', [LeagueController::class, 'update']);
    Route::delete('/leagues/{league}', [LeagueController::class, 'destroy']);

    // League Members
    Route::post('/leagues/join', [LeagueMemberController::class, 'joinByCode']);
    Route::get('/leagues/{league}/members', [LeagueMemberController::class, 'index']);
    Route::post('/leagues/{league}/join', [LeagueMemberController::class, 'join']);
    Route::post('/leagues/{league}/leave', [LeagueMemberController::class, 'leave']);
    Route::delete('/leagues/{league}/members/{user}', [LeagueMemberController::class, 'remove']);

    // Invite Code
    Route::get('/leagues/{league}/invite-code', [InviteCodeController::class, 'show']);
    Route::post('/leagues/{league}/invite-code/refresh', [InviteCodeController::class, 'refresh']);

    // Standings
    Route::get('/leagues/{league}/standings/month', [StandingsController::class, 'month']);
    Route::get('/leagues/{league}/standings/week', [StandingsController::class, 'week']);
    Route::get('/leagues/{league}/standings/yesterday', [StandingsController::class, 'yesterday']);
    Route::get('/leagues/{league}/today', [StandingsController::class, 'today']);

    // Items
    Route::get('/leagues/{league}/items', [ItemController::class, 'index']);
    Route::post('/leagues/{league}/items/{itemId}/use', [ItemController::class, 'use']);

    // Drafts
    Route::get('/leagues/{league}/drafts', [DraftController::class, 'index']);
    Route::get('/leagues/{league}/drafts/{draft}', [DraftController::class, 'show']);
    Route::post('/leagues/{league}/drafts/{draft}/pick', [DraftController::class, 'pick']);

    // Sync
    Route::post('/sync/steps', [SyncController::class, 'syncSteps'])
        ->middleware(['throttle:sync-steps', 'verify-app-attest']);

    // App Attest
    Route::post('/attest/challenge', [AttestController::class, 'challenge']);
    Route::post('/attest/register', [AttestController::class, 'register']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});
