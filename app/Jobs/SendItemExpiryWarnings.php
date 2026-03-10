<?php

namespace App\Jobs;

use App\Models\UserItem;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendItemExpiryWarnings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        // Items expiring in the next 24 hours
        $expiringItems = UserItem::query()
            ->whereNull('used_at')
            ->whereBetween('expires_at', [now(), now()->addHours(24)])
            ->with(['user', 'item', 'league'])
            ->get();

        foreach ($expiringItems as $userItem) {
            $notificationService->create(
                $userItem->user,
                $userItem->league,
                'item_expiring',
                "Item Expiring Soon [{$userItem->league->name}]",
                "Your {$userItem->item->name} in {$userItem->league->name} expires soon! Use it or lose it.",
            );
        }
    }
}
