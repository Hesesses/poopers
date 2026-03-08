<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DraftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->name,
            'name' => $this->name,
            'date' => $this->date,
            'status' => $this->status->name,
            'current_pick_index' => $this->current_pick_index,
            'pick_order' => $this->pick_order,
            'current_picker_user_id' => $this->currentPickerUserId(),
            'picks' => DraftPickResource::collection($this->whenLoaded('picks')),
            'remaining_items' => ItemResource::collection($this->whenAppended('remaining_items', collect())),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
