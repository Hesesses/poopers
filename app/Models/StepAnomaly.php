<?php

namespace App\Models;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use Database\Factories\StepAnomalyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepAnomaly extends Model
{
    /** @use HasFactory<StepAnomalyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'anomaly_type',
        'details',
        'severity',
        'reviewed',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'anomaly_type' => AnomalyType::class,
            'details' => 'array',
            'severity' => AnomalySeverity::class,
            'reviewed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
