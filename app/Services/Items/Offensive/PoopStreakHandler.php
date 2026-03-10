<?php

namespace App\Services\Items\Offensive;

use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class PoopStreakHandler extends BaseItemHandler
{
    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league);

        return $effect;
    }

    public function hasMidnightResolution(): bool
    {
        return true;
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Poop Streak!',
            'body' => 'If you finish last today, your streak will double!',
        ];
    }
}
