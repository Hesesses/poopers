<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncStepsRequest;
use App\Services\StepSyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(
        private StepSyncService $stepSyncService,
    ) {}

    public function syncSteps(SyncStepsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dailySteps = $this->stepSyncService->sync(
            $request->user(),
            $validated['steps'],
            $validated['date'] ?? null,
            $validated['hourly_steps'] ?? null,
        );

        return response()->json([
            'steps' => $dailySteps->steps,
            'modified_steps' => $dailySteps->modified_steps,
            'date' => $dailySteps->date->toDateString(),
        ]);
    }
}
