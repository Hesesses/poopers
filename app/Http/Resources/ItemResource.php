<?php

namespace App\Http\Resources;

use App\Enums\ItemRarity;
use App\Services\Items\ItemHandlerRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->name,
            'rarity' => $this->rarity->name,
            'effect' => $this->effect,
            'icon' => $this->icon,
            'requires_target' => app(ItemHandlerRegistry::class)->resolve($this->resource)->requiresTarget(),
            'requires_pro' => in_array($this->rarity, [ItemRarity::Rare, ItemRarity::Epic, ItemRarity::Legendary]),
        ];
    }
}
