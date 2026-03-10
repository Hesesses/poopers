<?php

namespace Database\Seeders;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemSource;
use App\Enums\LeagueMemberRole;
use App\Enums\StreakType;
use App\Models\DailySteps;
use App\Models\Item;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\LeagueDayResult;
use App\Models\LeagueMember;
use App\Models\LeagueNoonSnapshot;
use App\Models\Streak;
use App\Models\User;
use App\Models\UserItem;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PooperSeeder extends Seeder
{
    private User $me;

    private Carbon $startDate;

    private Carbon $endDate;

    public function run(): void
    {
        $this->startDate = Carbon::parse('2026-02-08');
        $this->endDate = Carbon::parse('2026-03-10');

        $this->me = $this->createMe();

        $leagues = $this->createLeagues();

        $this->generateStepHistory($leagues);
        $this->generateNoonSnapshots($leagues);
        $this->generateUsedItems();
        $this->generateUnusedItems();
        $this->generateStreaks($leagues);
    }

    private function createMe(): User
    {
        $user = User::find(1);

        if ($user) {
            return $user;
        }

        $user = User::forceCreate([
            'id' => 1,
            'email' => 'heikki@example.com',
            'first_name' => 'Heikki',
            'last_name' => 'Lampinen',
            'is_pro' => true,
            'pro_expires_at' => now()->addYear(),
        ]);

        DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");

        return $user;
    }

    /**
     * @return array<array{league: League, members: \Illuminate\Support\Collection<int, User>}>
     */
    private function createLeagues(): array
    {
        $leagueConfigs = [
            ['name' => 'The boys', 'icon' => '🍻', 'count' => 8, 'role' => LeagueMemberRole::Admin],
            ['name' => 'Family poop', 'icon' => '👨‍👩‍👧‍👦', 'count' => 4, 'role' => LeagueMemberRole::Admin],
            ['name' => 'Cardio is not lifting', 'icon' => '🏋️', 'count' => 12, 'role' => LeagueMemberRole::Member],
            ['name' => 'Acme Inc', 'icon' => '🏢', 'count' => 18, 'role' => LeagueMemberRole::Member],
        ];

        $leagues = [];

        foreach ($leagueConfigs as $config) {
            $isPro = $config['count'] > 6;

            $league = League::create([
                'name' => $config['name'],
                'icon' => $config['icon'],
                'timezone' => 'America/New_York',
                'invite_code' => League::generateInviteCode(),
                'created_by' => $this->me->id,
                'is_pro_league' => $isPro,
            ]);

            LeagueMember::create([
                'league_id' => $league->id,
                'user_id' => $this->me->id,
                'role' => LeagueMemberRole::Admin,
            ]);

            $fakeCount = $config['count'] - 1;
            $factory = $isPro ? User::factory()->pro() : User::factory();
            $fakeUsers = $factory->count($fakeCount)->create();

            foreach ($fakeUsers as $user) {
                LeagueMember::create([
                    'league_id' => $league->id,
                    'user_id' => $user->id,
                    'role' => LeagueMemberRole::Member,
                ]);
            }

            $allMembers = collect([$this->me])->merge($fakeUsers);
            $leagues[] = ['league' => $league, 'members' => $allMembers];
        }

        return $leagues;
    }

    /**
     * @param  array<array{league: League, members: \Illuminate\Support\Collection}>  $leagues
     */
    private function generateStepHistory(array $leagues): void
    {
        $dates = CarbonPeriod::create($this->startDate, $this->endDate);

        $userBaseSteps = [];

        foreach ($leagues as $leagueData) {
            foreach ($leagueData['members'] as $member) {
                if (! isset($userBaseSteps[$member->id])) {
                    $userBaseSteps[$member->id] = $member->id === $this->me->id
                        ? rand(8000, 14000)
                        : rand(5000, 15000);
                }
            }
        }

        $dailyStepsCache = [];

        foreach ($dates as $date) {
            $dateStr = $date->toDateString();

            foreach ($leagues as $leagueData) {
                $dayResults = [];

                foreach ($leagueData['members'] as $member) {
                    $skipChance = $date->isWeekend() ? 15 : 5;
                    if ($member->id !== $this->me->id && rand(1, 100) <= $skipChance) {
                        continue;
                    }

                    if (! isset($dailyStepsCache[$member->id][$dateStr])) {
                        $base = $userBaseSteps[$member->id];
                        $variance = $base * 0.3;
                        $steps = max(1000, (int) ($base + rand((int) -$variance, (int) $variance)));

                        $hourlySteps = $this->generateHourlySteps($steps);

                        DailySteps::create([
                            'user_id' => $member->id,
                            'date' => $dateStr,
                            'steps' => $steps,
                            'modified_steps' => $steps,
                            'hourly_steps' => $hourlySteps,
                            'last_synced_at' => $date->copy()->setTime(23, rand(0, 59)),
                        ]);

                        $dailyStepsCache[$member->id][$dateStr] = $steps;
                    }

                    $dayResults[] = [
                        'user_id' => $member->id,
                        'steps' => $dailyStepsCache[$member->id][$dateStr],
                    ];
                }

                usort($dayResults, fn ($a, $b) => $b['steps'] <=> $a['steps']);

                foreach ($dayResults as $position => $result) {
                    LeagueDayResult::create([
                        'league_id' => $leagueData['league']->id,
                        'user_id' => $result['user_id'],
                        'date' => $dateStr,
                        'steps' => $result['steps'],
                        'modified_steps' => $result['steps'],
                        'position' => $position + 1,
                        'is_winner' => $position === 0,
                        'is_last' => $position === count($dayResults) - 1,
                    ]);
                }
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function generateHourlySteps(int $totalSteps): array
    {
        $weights = [
            0, 0, 0, 0, 0, 0,         // 00-05: sleeping
            1, 3, 8, 6, 5, 7,         // 06-11: morning
            10, 8, 6, 5, 7, 9,        // 12-17: afternoon
            12, 8, 5, 3, 1, 0,        // 18-23: evening
        ];

        $totalWeight = array_sum($weights);
        $hourly = [];
        $allocated = 0;

        for ($h = 0; $h < 24; $h++) {
            if ($h === 23) {
                $hourly[$h] = $totalSteps - $allocated;
            } else {
                $base = ($weights[$h] / $totalWeight) * $totalSteps;
                $variance = $base * 0.3;
                $steps = max(0, (int) ($base + rand((int) -$variance, (int) $variance)));
                $hourly[$h] = $steps;
                $allocated += $steps;
            }
        }

        $hourly[23] = max(0, $hourly[23]);

        return $hourly;
    }

    /**
     * @param  array<array{league: League, members: \Illuminate\Support\Collection}>  $leagues
     */
    private function generateNoonSnapshots(array $leagues): void
    {
        $today = $this->endDate->toDateString();

        foreach ($leagues as $leagueData) {
            $memberSteps = DailySteps::query()
                ->whereIn('user_id', $leagueData['members']->pluck('id'))
                ->where('date', $today)
                ->get()
                ->keyBy('user_id');

            $sorted = $memberSteps->sortByDesc('modified_steps')->values();

            foreach ($sorted as $position => $steps) {
                LeagueNoonSnapshot::create([
                    'league_id' => $leagueData['league']->id,
                    'user_id' => $steps->user_id,
                    'date' => $today,
                    'steps' => (int) ($steps->steps * 0.55),
                    'modified_steps' => (int) ($steps->modified_steps * 0.55),
                    'position' => $position + 1,
                ]);
            }
        }
    }

    private function generateUsedItems(): void
    {
        $items = Item::all();
        if ($items->isEmpty()) {
            return;
        }

        $leagues = $this->me->leagues()->get();
        $usedCount = rand(10, 15);

        for ($i = 0; $i < $usedCount; $i++) {
            $item = $items->random();
            $league = $leagues->random();
            $usedDate = $this->startDate->copy()->addDays(rand(0, 30));

            $leagueMembers = $league->members()->where('users.id', '!=', $this->me->id)->get();
            $target = $leagueMembers->isNotEmpty() ? $leagueMembers->random() : null;

            $sources = [ItemSource::DailyWin, ItemSource::Draft, ItemSource::Bonus];

            $userItem = UserItem::create([
                'user_id' => $this->me->id,
                'league_id' => $league->id,
                'item_id' => $item->id,
                'source' => $sources[array_rand($sources)],
                'expires_at' => $usedDate->copy()->addDays(7),
                'used_at' => $usedDate,
                'used_on_user_id' => $target?->id,
            ]);

            ItemEffect::create([
                'user_item_id' => $userItem->id,
                'target_user_id' => $target?->id ?? $this->me->id,
                'league_id' => $league->id,
                'date' => $usedDate->toDateString(),
                'status' => ItemEffectStatus::Applied,
            ]);
        }
    }

    private function generateUnusedItems(): void
    {
        $items = Item::all();
        if ($items->isEmpty()) {
            return;
        }

        $leagues = $this->me->leagues()->get();
        $unusedCount = rand(10, 20);
        $sources = [ItemSource::DailyWin, ItemSource::Draft, ItemSource::Bonus, ItemSource::Loot];

        for ($i = 0; $i < $unusedCount; $i++) {
            UserItem::create([
                'user_id' => $this->me->id,
                'league_id' => $leagues->random()->id,
                'item_id' => $items->random()->id,
                'source' => $sources[array_rand($sources)],
                'expires_at' => now()->addDays(rand(1, 7)),
                'used_at' => null,
                'used_on_user_id' => null,
            ]);
        }
    }

    /**
     * @param  array<array{league: League, members: \Illuminate\Support\Collection}>  $leagues
     */
    private function generateStreaks(array $leagues): void
    {
        foreach ($leagues as $leagueData) {
            Streak::create([
                'user_id' => $this->me->id,
                'league_id' => $leagueData['league']->id,
                'type' => StreakType::Winning,
                'current_count' => rand(0, 5),
                'best_count' => rand(3, 8),
                'started_at' => $this->endDate->copy()->subDays(rand(0, 5)),
            ]);

            Streak::create([
                'user_id' => $this->me->id,
                'league_id' => $leagueData['league']->id,
                'type' => StreakType::NotLosing,
                'current_count' => rand(2, 12),
                'best_count' => rand(8, 20),
                'started_at' => $this->endDate->copy()->subDays(rand(2, 15)),
            ]);
        }
    }
}
