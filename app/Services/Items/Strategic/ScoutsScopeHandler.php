<?php

namespace App\Services\Items\Strategic;

use App\Models\DailySteps;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class ScoutsScopeHandler extends BaseItemHandler
{
    public function allowsSelfTarget(): bool
    {
        return false;
    }

    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        return $this->createEffect($userItem, $target, $league);
    }

    public function getResponseData(ItemEffect $effect): ?array
    {
        $steps = DailySteps::query()
            ->where('user_id', $effect->target_user_id)
            ->where('date', now()->toDateString())
            ->value('steps');

        return ['steps' => $steps ?? 0];
    }
}
