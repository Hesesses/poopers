<?php

namespace App\Models;

use App\Enums\ItemRarity;
use App\Enums\ItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'type',
        'rarity',
        'effect',
        'icon',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'rarity' => ItemRarity::class,
            'effect' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
