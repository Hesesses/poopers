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
        bool $sendPush = true,
    ): Notification {
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'league_id' => $league?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        if ($sendPush) {
            $this->sendPush($user, $title, $body, $data);
        }

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
                'include_aliases' => ['external_id' => ["user-{$user->id}"]],
                'target_channel' => 'push',
            ];

            if ($data) {
                $payload['data'] = $data;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Key '.config('onesignal.rest_api_key'),
            ])
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
        $externalIds = User::pluck('id')
            ->map(fn ($id) => "user-{$id}")
            ->values()
            ->all();

        if (empty($externalIds)) {
            return;
        }

        // OneSignal allows max 2000 aliases per request
        $chunks = array_chunk($externalIds, 2000);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Key '.config('onesignal.rest_api_key'),
                ])
                    ->post(config('onesignal.rest_api_url').'/notifications', [
                        'app_id' => config('onesignal.app_id'),
                        'include_aliases' => ['external_id' => $chunk],
                        'target_channel' => 'push',
                        'content_available' => true,
                        'apns_push_type_override' => 'background',
                        'data' => ['sync_type' => 'steps'],
                    ])
                    ->throw();

                Log::info('OneSignal silent push sent', [
                    'response' => $response->json(),
                    'user_count' => count($chunk),
                ]);
            } catch (\Throwable $e) {
                Log::error('OneSignal silent push failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
