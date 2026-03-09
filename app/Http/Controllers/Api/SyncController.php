<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncStepsBatchRequest;
use App\Http\Requests\SyncStepsRequest;
use App\Services\NotificationService;
use App\Services\StepSyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(
        private StepSyncService $stepSyncService,
        private NotificationService $notificationService,
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

        $this->notificationService->sendPush(
            $request->user(),
            'Steps Synced',
            "Synced {$dailySteps->steps} steps for {$dailySteps->date->toDateString()}",
        );

        return response()->json([
            'steps' => $dailySteps->steps,
            'modified_steps' => $dailySteps->modified_steps,
            'date' => $dailySteps->date->toDateString(),
        ]);
    }

    public function syncStepsBatch(SyncStepsBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $results = [];
        $totalSteps = 0;

        foreach ($validated['days'] as $day) {
            $dailySteps = $this->stepSyncService->sync(
                $request->user(),
                $day['steps'],
                $day['date'],
            );

            $totalSteps += $dailySteps->steps;

            $results[] = [
                'steps' => $dailySteps->steps,
                'modified_steps' => $dailySteps->modified_steps,
                'date' => $dailySteps->date->toDateString(),
            ];
        }

        $this->notificationService->sendPush(
            $request->user(),
            'Steps Synced',
            "Synced {$totalSteps} steps across ".count($results).' days',
        );

        return response()->json(['results' => $results]);
    }
}
