<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item' => new ItemResource($this->whenLoaded('item')),
            'source' => $this->source->name,
            'expires_at' => $this->expires_at,
            'used_at' => $this->used_at,
            'is_used' => $this->isUsed(),
            'is_expired' => $this->isExpired(),
        ];
    }
}
