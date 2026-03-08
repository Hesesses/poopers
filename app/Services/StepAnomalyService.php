<?php

namespace App\Services;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\StepAnomaly;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class StepAnomalyService
{
    public function flag(User $user, string $date, AnomalyType $type, AnomalySeverity $severity, array $details = []): void
    {
        try {
            StepAnomaly::create([
                'user_id' => $user->id,
                'date' => $date,
                'anomaly_type' => $type,
                'details' => $details,
                'severity' => $severity,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record step anomaly', [
                'user_id' => $user->id,
                'type' => $type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
