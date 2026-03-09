<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'avatar',
        'is_pro',
        'pro_expires_at',
        'onesignal_player_id',
        'notification_settings',
        'daily_steps_goal',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_pro' => 'boolean',
            'pro_expires_at' => 'datetime',
            'notification_settings' => 'array',
            'daily_steps_goal' => 'integer',
        ];
    }

    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class, 'league_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(UserItem::class);
    }

    public function streaks(): HasMany
    {
        return $this->hasMany(Streak::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function dailySteps(): HasMany
    {
        return $this->hasMany(DailySteps::class);
    }

    public function stepAnomalies(): HasMany
    {
        return $this->hasMany(StepAnomaly::class);
    }

    public function deviceAttestations(): HasMany
    {
        return $this->hasMany(DeviceAttestation::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isPro(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', now());
            })
            ->exists();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getStreak(string $leagueId, string $type): int
    {
        return $this->streaks()
            ->where('league_id', $leagueId)
            ->where('type', $type)
            ->value('current_count') ?? 0;
    }
}
