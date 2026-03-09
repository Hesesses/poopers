<?php

namespace App\Console\Commands;

use App\Enums\ItemSource;
use App\Enums\LeagueMemberRole;
use App\Models\Item;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\UserItem;
use Illuminate\Console\Command;

class SeedTestUserCommand extends Command
{
    protected $signature = 'seed:test-user {leagueId}';

    protected $description = 'Create a test opponent in a league and give user ID 1 all items';

    public function handle(): void
    {
        $league = League::query()->findOrFail($this->argument('leagueId'));
        $me = User::query()->findOrFail(1);

        $opponent = User::query()->create([
            'email' => 'test-'.uniqid().'@example.com',
            'first_name' => 'Test',
            'last_name' => 'Opponent',
        ]);

        LeagueMember::query()->create([
            'league_id' => $league->id,
            'user_id' => $opponent->id,
            'role' => LeagueMemberRole::Member,
        ]);

        $items = Item::all();
        foreach ($items as $item) {
            UserItem::query()->create([
                'user_id' => $me->id,
                'league_id' => $league->id,
                'item_id' => $item->id,
                'source' => ItemSource::Bonus,
                'expires_at' => now()->addDays(7),
            ]);
        }

        $this->info("Created opponent: {$opponent->full_name} (ID: {$opponent->id})");
        $this->info("Added to league: {$league->name}");
        $this->info("Gave you (ID: 1) {$items->count()} items.");
    }
}
