<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class League extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'icon',
        'timezone',
        'invite_code',
        'created_by',
        'is_pro_league',
    ];

    protected function casts(): array
    {
        return [
            'is_pro_league' => 'boolean',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dayResults(): HasMany
    {
        return $this->hasMany(LeagueDayResult::class);
    }

    public function noonSnapshots(): HasMany
    {
        return $this->hasMany(LeagueNoonSnapshot::class);
    }

    public function userItems(): HasMany
    {
        return $this->hasMany(UserItem::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class);
    }

    public function leagueMembers(): HasMany
    {
        return $this->hasMany(LeagueMember::class);
    }

    public function memberCount(): int
    {
        return $this->members()->count();
    }

    public function requiresPro(): bool
    {
        return $this->memberCount() >= 6;
    }

    public static function generateInviteCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }
}
