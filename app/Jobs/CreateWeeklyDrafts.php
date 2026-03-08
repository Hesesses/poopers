<?php

namespace App\Jobs;

use App\Models\League;
use App\Services\DraftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateWeeklyDrafts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DraftService $draftService): void
    {
        League::query()->chunk(100, function ($leagues) use ($draftService) {
            foreach ($leagues as $league) {
                if ($league->memberCount() < 2) {
                    continue;
                }

                $draftService->createDraft($league);
            }
        });
    }
}
