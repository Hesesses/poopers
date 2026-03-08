<?php

namespace App\Models;

use App\Enums\LeagueMemberRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueMember extends Model
{
    protected $fillable = [
        'league_id',
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => LeagueMemberRole::class,
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

    public function isAdmin(): bool
    {
        return $this->role === LeagueMemberRole::Admin;
    }
}
