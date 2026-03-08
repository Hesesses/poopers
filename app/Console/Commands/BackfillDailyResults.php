<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Services\DailyResultService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillDailyResults extends Command
{
    protected $signature = 'daily-results:backfill
                            {--from= : Start date (Y-m-d), defaults to 1st of current month}
                            {--to= : End date (Y-m-d), defaults to yesterday}';

    protected $description = 'Backfill daily results for all leagues within a date range';

    public function handle(DailyResultService $dailyResultService): int
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : now()->startOfMonth();

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))
            : now()->subDay();

        $leagues = League::query()->with('members')->get();

        $this->info("Backfilling results from {$from->toDateString()} to {$to->toDateString()} for {$leagues->count()} leagues...");

        $created = 0;

        foreach ($leagues as $league) {
            $date = $from->copy();

            while ($date->lte($to)) {
                $dailyResultService->calculateForLeague($league, $date->toDateString(), awardItems: false);
                $created++;
                $date->addDay();
            }
        }

        $this->info("Processed {$created} league-date combinations.");

        return self::SUCCESS;
    }
}
