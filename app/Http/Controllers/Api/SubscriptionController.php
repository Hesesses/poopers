<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'original_transaction_id' => ['required', 'string'],
            'product_id' => ['required', 'string'],
            'transaction_id' => ['required', 'string'],
        ]);

        $user = $request->user();

        $isYearly = str_contains($validated['product_id'], 'yearly');
        $periodEnd = $isYearly ? now()->addYear() : now()->addMonth();

        Subscription::updateOrCreate(
            ['original_transaction_id' => $validated['original_transaction_id']],
            [
                'user_id' => $user->id,
                'product_id' => $validated['product_id'],
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]
        );

        return response()->json([
            'message' => 'Subscription activated.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        $notificationType = $payload['notificationType'] ?? null;
        $originalTransactionId = $payload['data']['signedTransactionInfo']['originalTransactionId'] ?? null;

        if (! $originalTransactionId) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $subscription = Subscription::where('original_transaction_id', $originalTransactionId)->first();

        if (! $subscription) {
            return response()->json(['message' => 'Subscription not found.'], 404);
        }

        match ($notificationType) {
            'DID_RENEW' => $this->handleRenewal($subscription),
            'EXPIRED', 'REVOKE' => $this->handleExpiration($subscription),
            'DID_CHANGE_RENEWAL_STATUS' => $this->handleRenewalStatusChange($subscription, $payload),
            default => null,
        };

        return response()->json(['message' => 'Webhook processed.']);
    }

    private function handleRenewal(Subscription $subscription): void
    {
        $isYearly = str_contains($subscription->product_id, 'yearly');
        $periodEnd = $isYearly ? now()->addYear() : now()->addMonth();

        $subscription->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
        ]);
    }

    private function handleExpiration(Subscription $subscription): void
    {
        $subscription->update(['status' => 'expired']);
    }

    private function handleRenewalStatusChange(Subscription $subscription, array $payload): void
    {
        $autoRenewStatus = $payload['data']['signedTransactionInfo']['autoRenewStatus'] ?? null;

        if ($autoRenewStatus === 0) {
            $subscription->update(['status' => 'canceling']);
        }
    }
}
