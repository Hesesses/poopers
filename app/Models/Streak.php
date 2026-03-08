<?php

namespace App\Models;

use App\Enums\StreakType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Streak extends Model
{
    protected $fillable = [
        'user_id',
        'league_id',
        'type',
        'current_count',
        'best_count',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StreakType::class,
            'started_at' => 'date',
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
}
