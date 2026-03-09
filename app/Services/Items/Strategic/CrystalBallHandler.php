<?php

namespace App\Services\Items\Strategic;

use App\Models\DailySteps;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class CrystalBallHandler extends BaseItemHandler
{
    public function requiresTarget(): bool
    {
        return false;
    }

    public function allowsSelfTarget(): bool
    {
        return true;
    }

    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        return $this->createEffect($userItem, $user, $league);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseData(ItemEffect $effect): ?array
    {
        $league = League::query()->with('members')->find($effect->league_id);
        $today = now()->toDateString();
        $hour = now()->setTimezone($league->timezone)->hour;

        if ($hour === 0) {
            $hour = 1;
        }

        $steps = DailySteps::query()
            ->whereIn('user_id', $league->members->pluck('id'))
            ->where('date', $today)
            ->get()
            ->keyBy('user_id');

        $projections = $league->members->map(function ($member) use ($steps, $hour) {
            $current = $steps->get($member->id)?->modified_steps ?? 0;
            $projected = (int) round($current * (24 / max($hour, 1)));

            return (object) [
                'user_id' => $member->id,
                'user_name' => $member->full_name,
                'current_steps' => $current,
                'projected_steps' => $projected,
            ];
        })->sortByDesc('projected_steps')->values();

        return ['projections' => $projections->toArray()];
    }
}
