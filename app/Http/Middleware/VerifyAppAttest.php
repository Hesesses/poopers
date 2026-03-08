<?php

namespace App\Http\Middleware;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\DeviceAttestation;
use App\Services\AntiCheat\AppAttestService;
use App\Services\StepAnomalyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyAppAttest
{
    public function __construct(
        private AppAttestService $appAttestService,
        private StepAnomalyService $anomalyService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('anticheat.enabled') || ! config('anticheat.layers.app_attest')) {
            return $next($request);
        }

        $keyId = $request->header('X-App-Attest-Key');
        $assertion = $request->header('X-App-Assertion');

        if (! $keyId || ! $assertion) {
            $this->flagMissing($request);

            return $next($request);
        }

        try {
            $attestation = DeviceAttestation::query()
                ->where('key_id', $keyId)
                ->where('user_id', $request->user()?->id)
                ->first();

            if (! $attestation) {
                $this->flagInvalid($request, 'Unknown key ID');

                return $next($request);
            }

            $clientDataHash = hash('sha256', $request->getContent(), true);

            $verified = $this->appAttestService->verifyAssertion(
                $attestation,
                $assertion,
                $clientDataHash,
            );

            if (! $verified) {
                $this->flagInvalid($request, 'Assertion verification failed');
            }
        } catch (\Throwable $e) {
            Log::warning('App Attest verification error', ['error' => $e->getMessage()]);
            $this->flagInvalid($request, $e->getMessage());
        }

        return $next($request);
    }

    private function flagMissing(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }

        $this->anomalyService->flag(
            $user,
            now()->toDateString(),
            AnomalyType::AttestFailed,
            AnomalySeverity::High,
            ['reason' => 'Missing attest headers'],
        );
    }

    private function flagInvalid(Request $request, string $reason): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }

        $this->anomalyService->flag(
            $user,
            now()->toDateString(),
            AnomalyType::AttestFailed,
            AnomalySeverity::High,
            ['reason' => $reason],
        );
    }
}
