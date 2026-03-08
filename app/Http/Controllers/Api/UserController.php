<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserStatsResource;
use App\Services\UserStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserStatsService $userStatsService,
    ) {}

    public function stats(Request $request): UserStatsResource
    {
        $stats = $this->userStatsService->getStats($request->user());

        return new UserStatsResource($stats);
    }

    public function updateProfile(Request $request): UserResource
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return new UserResource($request->user()->fresh());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_settings' => ['required', 'array'],
            'notification_settings.push_enabled' => ['boolean'],
            'notification_settings.daily_results' => ['boolean'],
            'notification_settings.draft_reminders' => ['boolean'],
            'notification_settings.attack_alerts' => ['boolean'],
            'onesignal_player_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $request->user();

        if (isset($validated['notification_settings'])) {
            $user->update(['notification_settings' => $validated['notification_settings']]);
        }

        if (isset($validated['onesignal_player_id'])) {
            $user->update(['onesignal_player_id' => $validated['onesignal_player_id']]);
        }

        return response()->json(['message' => 'Settings updated.']);
    }

    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'is_pro' => $user->isPro(),
            'pro_expires_at' => $user->pro_expires_at?->toIso8601String(),
        ]);
    }
}
