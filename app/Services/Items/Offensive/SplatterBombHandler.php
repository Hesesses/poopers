<?php

namespace App\Services\Items\Offensive;

use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class SplatterBombHandler extends BaseItemHandler
{
    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league);
        $this->recalculateSteps($target);

        return $effect;
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Splatter Bomb!',
            'body' => 'You were hit by a Splatter Bomb! -5% steps.',
        ];
    }
}
