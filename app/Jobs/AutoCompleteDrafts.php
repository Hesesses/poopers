<?php

namespace App\Jobs;

use App\Enums\DraftStatus;
use App\Models\Draft;
use App\Services\DraftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoCompleteDrafts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DraftService $draftService): void
    {
        $drafts = Draft::query()
            ->where('status', DraftStatus::InProgress)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($drafts as $draft) {
            // Auto-assign all remaining picks
            while ($draft->currentPickerUserId() !== null) {
                $pick = $draftService->autoAssignPick($draft);
                if (! $pick) {
                    break;
                }
                $draft->refresh();
            }

            if ($draft->status !== DraftStatus::Completed) {
                $draft->update(['status' => DraftStatus::Completed]);
            }
        }
    }
}
