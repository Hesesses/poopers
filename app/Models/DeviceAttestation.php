<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceAttestation extends Model
{
    protected $fillable = [
        'user_id',
        'key_id',
        'public_key',
        'sign_count',
        'environment',
    ];

    protected function casts(): array
    {
        return [
            'sign_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
