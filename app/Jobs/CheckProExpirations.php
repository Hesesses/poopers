<?php

namespace App\Jobs;

use App\Models\LeagueMember;
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
        // Find users whose PRO has expired
        $expiredUsers = User::query()
            ->where('is_pro', true)
            ->whereNotNull('pro_expires_at')
            ->where('pro_expires_at', '<', now())
            ->get();

        foreach ($expiredUsers as $user) {
            $user->update(['is_pro' => false]);

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

                // Check if league should be downgraded
                if ($member->league->memberCount() < 6) {
                    $member->league->update(['is_pro_league' => false]);
                }
            }
        }
    }
}
