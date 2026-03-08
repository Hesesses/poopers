<?php

namespace App\Services\AntiCheat;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\DailySteps;
use App\Models\User;
use App\Services\StepAnomalyService;

class StepHeuristicsService
{
    public function __construct(
        private StepAnomalyService $anomalyService,
    ) {}

    public function check(User $user, int $steps, string $date, ?DailySteps $existing): void
    {
        $this->checkMaxExceeded($user, $steps, $date);
        $this->checkStepsDecreased($user, $steps, $date, $existing);
        $this->checkLargeJump($user, $steps, $date, $existing);
        $this->checkRoundNumber($user, $steps, $date);
    }

    private function checkMaxExceeded(User $user, int $steps, string $date): void
    {
        if ($steps > config('anticheat.thresholds.max_daily_steps')) {
            $this->anomalyService->flag($user, $date, AnomalyType::MaxExceeded, AnomalySeverity::High, [
                'steps' => $steps,
                'threshold' => config('anticheat.thresholds.max_daily_steps'),
            ]);
        }
    }

    private function checkStepsDecreased(User $user, int $steps, string $date, ?DailySteps $existing): void
    {
        if ($existing && $steps < $existing->steps) {
            $this->anomalyService->flag($user, $date, AnomalyType::StepsDecreased, AnomalySeverity::Medium, [
                'previous_steps' => $existing->steps,
                'new_steps' => $steps,
            ]);
        }
    }

    private function checkLargeJump(User $user, int $steps, string $date, ?DailySteps $existing): void
    {
        if (! $existing) {
            return;
        }

        $delta = abs($steps - $existing->steps);

        if ($delta > config('anticheat.thresholds.large_jump')) {
            $this->anomalyService->flag($user, $date, AnomalyType::LargeJump, AnomalySeverity::Medium, [
                'previous_steps' => $existing->steps,
                'new_steps' => $steps,
                'delta' => $delta,
            ]);
        }
    }

    private function checkRoundNumber(User $user, int $steps, string $date): void
    {
        $minimum = config('anticheat.thresholds.round_number_minimum');
        $divisor = config('anticheat.thresholds.round_number_divisor');

        if ($steps >= $minimum && $steps % $divisor === 0) {
            $this->anomalyService->flag($user, $date, AnomalyType::RoundNumber, AnomalySeverity::Low, [
                'steps' => $steps,
            ]);
        }
    }
}
