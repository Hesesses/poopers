<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySteps extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'steps',
        'modified_steps',
        'hourly_steps',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hourly_steps' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
