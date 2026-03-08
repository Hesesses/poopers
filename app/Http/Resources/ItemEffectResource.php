<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemEffectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->name,
            'date' => $this->date,
            'blocked_by_item_id' => $this->blocked_by_item_id,
            'created_at' => $this->created_at,
        ];
    }
}
