<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueDayResult extends Model
{
    protected $fillable = [
        'league_id',
        'user_id',
        'date',
        'steps',
        'modified_steps',
        'position',
        'is_winner',
        'is_last',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_winner' => 'boolean',
            'is_last' => 'boolean',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
