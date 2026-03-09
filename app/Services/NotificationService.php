<?php

namespace App\Services;

use App\Models\League;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function create(
        User $user,
        ?League $league,
        string $type,
        string $title,
        string $body,
        ?array $data = null,
    ): Notification {
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'league_id' => $league?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        $this->sendPush($user, $title, $body, $data);

        return $notification;
    }

    public function sendPush(User $user, string $title, string $body, ?array $data = null): void
    {
        $settings = $user->notification_settings ?? [];

        // Check if user has notifications enabled for this type
        if (! ($settings['push_enabled'] ?? true)) {
            return;
        }

        try {
            $payload = [
                'app_id' => config('onesignal.app_id'),
                'contents' => ['en' => $body],
                'headings' => ['en' => $title],
                'include_aliases' => ['external_id' => [(string) $user->id]],
                'target_channel' => 'push',
            ];

            if ($data) {
                $payload['data'] = $data;
            }

            $response = Http::withToken(config('onesignal.rest_api_key'))
                ->post(config('onesignal.rest_api_url').'/notifications', $payload)
                ->throw();

            Log::info('OneSignal push sent', [
                'user_id' => $user->id,
                'response' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('OneSignal push notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendSilentPush(): void
    {
        try {
            Http::withToken(config('onesignal.rest_api_key'))
                ->post(config('onesignal.rest_api_url').'/notifications', [
                    'app_id' => config('onesignal.app_id'),
                    'included_segments' => ['All'],
                    'content_available' => true,
                    'data' => ['sync_type' => 'steps'],
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::error('OneSignal silent push failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
