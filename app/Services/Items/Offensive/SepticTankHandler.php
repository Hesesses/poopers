<?php

namespace App\Services\Items\Offensive;

use App\Enums\ItemEffectStatus;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;
use App\Services\Items\DefenseResolver;

class SepticTankHandler extends BaseItemHandler
{
    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league, ItemEffectStatus::Pending);

        $defense = app(DefenseResolver::class)->resolve($effect, $userItem, $target, $league);

        if ($defense->blocked || $defense->reflected || $defense->missed) {
            return $effect->fresh();
        }

        $effect->update(['status' => ItemEffectStatus::Applied]);

        return $effect->fresh();
    }

    public function hasMidnightResolution(): bool
    {
        return true;
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Septic Tank!',
            'body' => 'Septic Tank attached! More steps = more reduction.',
        ];
    }
}
