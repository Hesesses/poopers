<?php

namespace App\Models;

use App\Enums\ItemSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserItem extends Model
{
    protected $fillable = [
        'user_id',
        'league_id',
        'item_id',
        'source',
        'expires_at',
        'used_at',
        'used_on_user_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => ItemSource::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_on_user_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(ItemEffect::class);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
