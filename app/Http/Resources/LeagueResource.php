<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'timezone' => $this->timezone,
            'invite_code' => $this->invite_code,
            'member_count' => $this->members->count(),
            'is_pro_league' => $this->is_pro_league,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
