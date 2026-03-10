<?php

namespace App\Models;

use App\Enums\DraftStatus;
use App\Enums\DraftType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Draft extends Model
{
    protected $fillable = [
        'league_id',
        'type',
        'name',
        'date',
        'available_items',
        'pick_order',
        'current_pick_index',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DraftType::class,
            'date' => 'date',
            'available_items' => 'array',
            'pick_order' => 'array',
            'status' => DraftStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function picks(): HasMany
    {
        return $this->hasMany(DraftPick::class);
    }

    public function currentPickerUserId(): ?int
    {
        if ($this->current_pick_index >= count($this->pick_order)) {
            return null;
        }

        return $this->pick_order[$this->current_pick_index];
    }

    public function isComplete(): bool
    {
        return $this->status === DraftStatus::Completed;
    }
}
