<?php

namespace Database\Seeders;

use App\Enums\ItemSource;
use App\Enums\LeagueMemberRole;
use App\Models\Item;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\UserItem;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run(int $leagueId): void
    {
        $league = League::query()->findOrFail($leagueId);
        $me = User::query()->findOrFail(1);

        // Create a test opponent and add to league
        $opponent = User::factory()->create();

        LeagueMember::query()->create([
            'league_id' => $league->id,
            'user_id' => $opponent->id,
            'role' => LeagueMemberRole::Member,
        ]);

        // Give me one of each item
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

        $this->command->info("Created opponent: {$opponent->full_name} (ID: {$opponent->id})");
        $this->command->info("Added to league: {$league->name}");
        $this->command->info("Gave you (ID: 1) {$items->count()} items.");
    }
}
