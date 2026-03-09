<?php

namespace App\Jobs;

use App\Models\LeagueMember;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckProExpirations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        // Mark expired subscriptions
        Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->update(['status' => 'expired']);

        // Find users who were pro but no longer have active subscriptions
        $expiredUsers = User::query()
            ->whereHas('subscriptions', fn ($q) => $q->where('status', 'expired'))
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('status', 'active')
                ->where(fn ($q2) => $q2->whereNull('current_period_end')->orWhere('current_period_end', '>', now()))
            )
            ->get();

        foreach ($expiredUsers as $user) {
            // Remove from 6+ member leagues
            $proLeagueMembers = LeagueMember::query()
                ->where('user_id', $user->id)
                ->whereHas('league', fn ($q) => $q->where('is_pro_league', true))
                ->with('league')
                ->get();

            foreach ($proLeagueMembers as $member) {
                $notificationService->create(
                    $user,
                    $member->league,
                    'pro_expired',
                    'PRO Expired',
                    "You've been removed from {$member->league->name} because your PRO subscription expired.",
                );

                $member->delete();

                if ($member->league->memberCount() < 6) {
                    $member->league->update(['is_pro_league' => false]);
                }
            }
        }
    }
}
