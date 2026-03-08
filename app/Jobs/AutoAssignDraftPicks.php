<?php

namespace App\Jobs;

use App\Enums\DraftStatus;
use App\Models\Draft;
use App\Models\DraftPick;
use App\Services\DraftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoAssignDraftPicks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DraftService $draftService): void
    {
        $drafts = Draft::query()
            ->where('status', DraftStatus::InProgress)
            ->get();

        foreach ($drafts as $draft) {
            $lastPick = DraftPick::query()
                ->where('draft_id', $draft->id)
                ->orderByDesc('picked_at')
                ->first();

            $lastActivity = $lastPick?->picked_at ?? $draft->created_at;

            // 4 hour timeout per pick
            if ($lastActivity->diffInHours(now()) >= 4) {
                $draftService->autoAssignPick($draft);
            }
        }
    }
}
