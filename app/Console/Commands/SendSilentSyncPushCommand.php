<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendSilentSyncPushCommand extends Command
{
    protected $signature = 'sync:send-silent-push';

    protected $description = 'Manually send a silent push notification to trigger steps sync on all devices';

    public function handle(NotificationService $notificationService): int
    {
        $this->info('Sending silent push notification...');

        $notificationService->sendSilentPush();

        $this->info('Silent push notification sent. Check logs for OneSignal response.');

        return self::SUCCESS;
    }
}
