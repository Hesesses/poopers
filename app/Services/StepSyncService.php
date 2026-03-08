<?php

namespace App\Services;

use App\Enums\ItemEffectStatus;
use App\Models\DailySteps;
use App\Models\ItemEffect;
use App\Models\User;
use App\Services\AntiCheat\StepHeuristicsService;
use App\Services\AntiCheat\StepVelocityService;

class StepSyncService
{
    public function __construct(
        private StepHeuristicsService $heuristicsService,
        private StepVelocityService $velocityService,
    ) {}

    /**
     * @param  array<int, int>|null  $hourlySteps
     */
    public function sync(User $user, int $steps, ?string $date = null, ?array $hourlySteps = null): DailySteps
    {
        $date ??= now()->toDateString();

        $existing = DailySteps::query()
            ->where('user_id', $user->id)
            ->where('date', $date)
            ->first();

        if (config('anticheat.enabled')) {
            if (config('anticheat.layers.heuristics')) {
                $this->heuristicsService->check($user, $steps, $date, $existing);
            }

            if (config('anticheat.layers.velocity') && $hourlySteps !== null) {
                $this->velocityService->check($user, $steps, $date, $hourlySteps);
            }
        }

        $updateData = [
            'steps' => $steps,
            'modified_steps' => $steps,
            'last_synced_at' => now(),
        ];

        if ($hourlySteps !== null) {
            $updateData['hourly_steps'] = $hourlySteps;
        }

        $dailySteps = DailySteps::query()->updateOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            $updateData,
        );

        $this->recalculateModifiedSteps($user, $date);

        return $dailySteps->fresh();
    }

    public function recalculateModifiedSteps(User $user, string $date): void
    {
        $dailySteps = DailySteps::query()
            ->where('user_id', $user->id)
            ->where('date', $date)
            ->first();

        if (! $dailySteps) {
            return;
        }

        $modifiedSteps = $dailySteps->steps;

        $effects = ItemEffect::query()
            ->where('target_user_id', $user->id)
            ->where('date', $date)
            ->where('status', ItemEffectStatus::Applied)
            ->with('userItem.item')
            ->get();

        foreach ($effects as $effect) {
            $modifiedSteps = $this->applyEffect($modifiedSteps, $effect);
        }

        // Also apply self-boost effects
        $boostEffects = ItemEffect::query()
            ->whereHas('userItem', fn ($q) => $q->where('user_id', $user->id))
            ->where('target_user_id', $user->id)
            ->where('date', $date)
            ->where('status', ItemEffectStatus::Applied)
            ->with('userItem.item')
            ->get();

        foreach ($boostEffects as $effect) {
            if (! $effects->contains($effect)) {
                $modifiedSteps = $this->applyEffect($modifiedSteps, $effect);
            }
        }

        $dailySteps->update(['modified_steps' => max(0, $modifiedSteps)]);
    }

    private function applyEffect(int $steps, ItemEffect $effect): int
    {
        $item = $effect->userItem->item;
        $effectData = $item->effect;

        return match ($effectData['type'] ?? null) {
            'reduce_steps' => $this->applyReduction($steps, $effectData),
            'boost_steps' => $this->applyBoost($steps, $effectData),
            default => $steps,
        };
    }

    private function applyReduction(int $steps, array $effectData): int
    {
        if (($effectData['unit'] ?? '') === 'percent') {
            return (int) round($steps * (1 - $effectData['value'] / 100));
        }

        return $steps - ($effectData['value'] ?? 0);
    }

    private function applyBoost(int $steps, array $effectData): int
    {
        if (($effectData['unit'] ?? '') === 'percent') {
            return (int) round($steps * (1 + $effectData['value'] / 100));
        }

        return $steps + ($effectData['value'] ?? 0);
    }
}
