<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name ?? '',
            'last_name' => $this->last_name ?? '',
            'full_name' => $this->full_name ?? '',
            'avatar' => $this->avatar,
            'is_pro' => $this->isPro(),
            'created_at' => $this->created_at?->toIso8601String(),
            'notification_settings' => $this->notification_settings ?? [
                'push_enabled' => true,
                'daily_results' => true,
                'draft_reminders' => true,
                'attack_alerts' => true,
            ],
        ];
    }
}
