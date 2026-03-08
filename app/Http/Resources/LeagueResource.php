<?php

namespace App\Http\Resources;

use App\Models\LeagueDayResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $yourPosition = null;

        if ($request->user()) {
            $yesterday = now()->subDay()->toDateString();
            $result = LeagueDayResult::query()
                ->where('league_id', $this->id)
                ->where('user_id', $request->user()->id)
                ->where('date', $yesterday)
                ->first();

            $yourPosition = $result?->position;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'timezone' => $this->timezone,
            'invite_code' => $this->invite_code,
            'member_count' => $this->members->count(),
            'your_position' => $yourPosition,
            'is_pro_league' => $this->is_pro_league,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
