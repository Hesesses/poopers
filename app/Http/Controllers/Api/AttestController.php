<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AntiCheat\AppAttestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AttestController extends Controller
{
    public function __construct(
        private AppAttestService $appAttestService,
    ) {}

    public function challenge(Request $request): JsonResponse
    {
        $nonce = Str::random(32);
        $userId = $request->user()->id;

        Cache::put("attest_challenge:{$userId}", $nonce, now()->addMinutes(5));

        return response()->json(['challenge' => $nonce]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key_id' => ['required', 'string'],
            'attestation' => ['required', 'string'],
        ]);

        $userId = $request->user()->id;
        $challenge = Cache::pull("attest_challenge:{$userId}");

        if (! $challenge) {
            return response()->json(['error' => 'Challenge expired or not found'], 422);
        }

        $result = $this->appAttestService->verifyAttestation(
            $validated['key_id'],
            $validated['attestation'],
            $challenge,
            $userId,
        );

        if (! $result) {
            return response()->json(['error' => 'Attestation verification failed'], 422);
        }

        return response()->json(['status' => 'registered']);
    }
}
