<?php

namespace App\Services\AntiCheat;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\User;
use App\Services\StepAnomalyService;

class StepVelocityService
{
    public function __construct(
        private StepAnomalyService $anomalyService,
    ) {}

    /**
     * @param  array<int, int>  $hourlySteps
     */
    public function check(User $user, int $totalSteps, string $date, array $hourlySteps): void
    {
        $this->checkHourlyMax($user, $date, $hourlySteps);
        $this->checkTotalMismatch($user, $totalSteps, $date, $hourlySteps);
        $this->checkSuspiciousNight($user, $date, $hourlySteps);
    }

    /**
     * @param  array<int, int>  $hourlySteps
     */
    private function checkHourlyMax(User $user, string $date, array $hourlySteps): void
    {
        $maxHourly = config('anticheat.thresholds.max_hourly_steps');

        foreach ($hourlySteps as $hour => $steps) {
            if ($steps > $maxHourly) {
                $this->anomalyService->flag($user, $date, AnomalyType::VelocityExceeded, AnomalySeverity::Medium, [
                    'hour' => $hour,
                    'steps' => $steps,
                    'threshold' => $maxHourly,
                ]);
            }
        }
    }

    /**
     * @param  array<int, int>  $hourlySteps
     */
    private function checkTotalMismatch(User $user, int $totalSteps, string $date, array $hourlySteps): void
    {
        $hourlyTotal = array_sum($hourlySteps);
        $tolerance = config('anticheat.thresholds.hourly_total_tolerance');

        if (abs($hourlyTotal - $totalSteps) > $tolerance) {
            $this->anomalyService->flag($user, $date, AnomalyType::HourlyMismatch, AnomalySeverity::High, [
                'reported_total' => $totalSteps,
                'hourly_total' => $hourlyTotal,
                'difference' => abs($hourlyTotal - $totalSteps),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $hourlySteps
     */
    private function checkSuspiciousNight(User $user, string $date, array $hourlySteps): void
    {
        $nightHours = config('anticheat.thresholds.night_hours');
        $threshold = config('anticheat.thresholds.suspicious_night_steps');

        $nightSteps = 0;
        foreach ($nightHours as $hour) {
            $nightSteps += $hourlySteps[$hour] ?? 0;
        }

        if ($nightSteps > $threshold) {
            $this->anomalyService->flag($user, $date, AnomalyType::SuspiciousNight, AnomalySeverity::Low, [
                'night_steps' => $nightSteps,
                'threshold' => $threshold,
            ]);
        }
    }
}
