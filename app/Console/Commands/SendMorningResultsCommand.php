<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Models\LeagueDayResult;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendMorningResultsCommand extends Command
{
    protected $signature = 'results:send-morning {--league= : Send for a specific league ID (bypasses timezone check)}';

    protected $description = 'Manually trigger morning results notifications to verify payload includes league_id';

    public function handle(NotificationService $notificationService): int
    {
        $leagueId = $this->option('league');

        if ($leagueId) {
            $league = League::with('members')->find($leagueId);

            if (! $league) {
                $this->error("League {$leagueId} not found.");

                return self::FAILURE;
            }

            return $this->sendForLeague($league, $notificationService);
        }

        $this->info('Dispatching SendMorningResults job (respects timezone check)...');
        \App\Jobs\SendMorningResults::dispatchSync();
        $this->info('Done. Check logs for OneSignal payloads.');

        return self::SUCCESS;
    }

    private function sendForLeague(League $league, NotificationService $notificationService): int
    {
        $yesterday = now()->setTimezone($league->timezone)->subDay()->toDateString();

        $winner = LeagueDayResult::query()
            ->where('league_id', $league->id)
            ->where('date', $yesterday)
            ->where('is_winner', true)
            ->with('user')
            ->first();

        if (! $winner) {
            $this->error("No winner found for league {$league->id} on {$yesterday}.");

            return self::FAILURE;
        }

        $this->info("Sending morning results for league '{$league->name}' (ID: {$league->id})...");
        $this->info("Winner: {$winner->user->full_name} with {$winner->modified_steps} steps");

        foreach ($league->members as $member) {
            $notificationService->create(
                $member,
                $league,
                'daily_results',
                'Yesterday\'s Results',
                "{$winner->user->full_name} won with {$winner->modified_steps} steps!",
                ['league_id' => $league->id],
            );
            $this->line("  → Sent to {$member->full_name}");
        }

        $this->info('Done. Verify OneSignal payload includes data.league_id.');

        return self::SUCCESS;
    }
}
