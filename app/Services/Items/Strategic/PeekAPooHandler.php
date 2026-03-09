<?php

namespace App\Services\Items\Strategic;

use App\Models\DailySteps;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class PeekAPooHandler extends BaseItemHandler
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

    public function getResponseData(ItemEffect $effect): ?array
    {
        $league = League::query()->with('members')->find($effect->league_id);
        $members = $league->members;
        $today = now()->toDateString();

        $steps = DailySteps::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where('date', $today)
            ->get()
            ->keyBy('user_id');

        $sorted = $members->sortByDesc(fn ($m) => $steps->get($m->id)?->modified_steps ?? 0)->values();
        $position = $sorted->search(fn ($m) => $m->id === $effect->target_user_id);

        return [
            'position' => $position !== false ? $position + 1 : null,
            'total' => $members->count(),
        ];
    }
}
