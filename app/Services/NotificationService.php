<?php

namespace App\Services;

use App\Models\League;
use App\Models\Notification;
use App\Models\User;
use Berkayk\OneSignal\OneSignalFacade as OneSignal;

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

        if (! $user->onesignal_player_id) {
            return;
        }

        try {
            OneSignal::sendNotificationToExternalUser(
                headings: $title,
                message: $body,
                userId: $user->onesignal_player_id,
                data: $data,
            );
        } catch (\Throwable) {
            // Silently fail push notifications
        }
    }

    public function sendSilentPush(): void
    {
        try {
            OneSignal::sendNotificationToAll(
                message: '',
                data: ['sync_type' => 'steps'],
            );
        } catch (\Throwable) {
            // Silently fail
        }
    }
}
