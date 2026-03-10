<?php

namespace App\Jobs;

use App\Enums\DraftStatus;
use App\Models\Draft;
use App\Models\DraftPick;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDraftReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        $drafts = Draft::query()
            ->where('status', DraftStatus::InProgress)
            ->with('league')
            ->get();

        foreach ($drafts as $draft) {
            $currentUserId = $draft->currentPickerUserId();
            if (! $currentUserId) {
                continue;
            }

            $lastPick = DraftPick::query()
                ->where('draft_id', $draft->id)
                ->orderByDesc('picked_at')
                ->first();

            $lastActivity = $lastPick?->picked_at ?? $draft->created_at;

            // Send reminder after 2 hours
            $hoursSince = $lastActivity->diffInHours(now());
            if ($hoursSince >= 2 && $hoursSince < 3) {
                $user = User::query()->find($currentUserId);
                if ($user) {
                    $notificationService->create(
                        $user,
                        $draft->league,
                        'draft_reminder',
                        "Draft Reminder [{$draft->league->name}]",
                        "It's still your turn to pick in {$draft->league->name}! You have ".(4 - $hoursSince).' hours left.',
                    );
                }
            }
        }
    }
}
