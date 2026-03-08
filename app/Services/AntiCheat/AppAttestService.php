<?php

namespace App\Services\AntiCheat;

use App\Models\DeviceAttestation;
use Illuminate\Support\Facades\Log;

class AppAttestService
{
    /**
     * Verify an attestation from Apple and store the device's public key.
     *
     * @return array{public_key: string, environment: string}|null
     */
    public function verifyAttestation(string $keyId, string $attestation, string $challenge, int $userId): ?array
    {
        try {
            $attestationData = base64_decode($attestation);

            // In production, this would decode CBOR, verify the certificate chain
            // against Apple's App Attest root CA, verify the nonce, and extract
            // the public key. Requires spomky-labs/cbor-php package.
            //
            // For now, we store the attestation and mark as development.
            // Full verification will be implemented when App Attest is enabled.

            $environment = config('anticheat.app_attest.production') ? 'production' : 'development';

            DeviceAttestation::query()->updateOrCreate(
                ['key_id' => $keyId],
                [
                    'user_id' => $userId,
                    'public_key' => base64_encode($attestationData),
                    'sign_count' => 0,
                    'environment' => $environment,
                ],
            );

            return [
                'public_key' => base64_encode($attestationData),
                'environment' => $environment,
            ];
        } catch (\Throwable $e) {
            Log::warning('Attestation verification failed', [
                'key_id' => $keyId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verify an assertion signature against a stored device attestation.
     */
    public function verifyAssertion(DeviceAttestation $attestation, string $assertion, string $clientDataHash): bool
    {
        try {
            // In production, this would:
            // 1. Decode the CBOR assertion
            // 2. Verify the signature using the stored public key
            // 3. Verify the authenticator data
            // 4. Check that sign_count > stored sign_count (replay protection)
            // 5. Update the stored sign_count
            //
            // Full verification will be implemented when App Attest is enabled.

            $attestation->increment('sign_count');

            return true;
        } catch (\Throwable $e) {
            Log::warning('Assertion verification failed', [
                'key_id' => $attestation->key_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
