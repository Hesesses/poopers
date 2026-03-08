<?php

namespace App\Models;

use App\Enums\ItemEffectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemEffect extends Model
{
    protected $fillable = [
        'user_item_id',
        'target_user_id',
        'league_id',
        'date',
        'status',
        'blocked_by_item_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => ItemEffectStatus::class,
        ];
    }

    public function userItem(): BelongsTo
    {
        return $this->belongsTo(UserItem::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
