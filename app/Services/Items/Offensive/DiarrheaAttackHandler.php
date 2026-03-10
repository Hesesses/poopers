<?php

namespace App\Services\Items\Offensive;

use App\Enums\ItemEffectStatus;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class DiarrheaAttackHandler extends BaseItemHandler
{
    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league);
        $this->recalculateSteps($target);

        ItemEffect::query()->create([
            'user_item_id' => $userItem->id,
            'target_user_id' => $user->id,
            'league_id' => $league->id,
            'date' => now()->toDateString(),
            'status' => ItemEffectStatus::Applied,
        ]);
        $this->recalculateSteps($user);

        return $effect;
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Diarrhea Attack!',
            'body' => '8% of your steps were stolen!',
        ];
    }
}
