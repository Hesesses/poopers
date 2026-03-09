<?php

namespace App\Services\Items\Defensive;

use App\Enums\ItemEffectStatus;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class TitaniumToiletHandler extends BaseItemHandler
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
        $effect = $this->createEffect($userItem, $user, $league);

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

    public function getUserNotification(ItemEffect $effect, ?User $target): ?array
    {
        return [
            'title' => 'Titanium Toilet',
            'body' => 'Titanium Toilet activated! All attacks blocked + 3% boost.',
        ];
    }
}
