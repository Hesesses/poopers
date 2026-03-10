<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueNoonSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\LeagueNoonSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'league_id',
        'user_id',
        'date',
        'steps',
        'modified_steps',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
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
