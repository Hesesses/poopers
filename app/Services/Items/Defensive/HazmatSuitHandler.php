<?php

namespace App\Services\Items\Defensive;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemType;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class HazmatSuitHandler extends BaseItemHandler
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
        ItemEffect::query()
            ->where('target_user_id', $user->id)
            ->where('league_id', $league->id)
            ->where('date', now()->toDateString())
            ->where('status', ItemEffectStatus::Applied)
            ->whereHas('userItem.item', fn ($q) => $q->where('type', ItemType::Offensive))
            ->update(['status' => ItemEffectStatus::Cancelled]);

        $effect = $this->createEffect($userItem, $user, $league);

        $this->recalculateSteps($user);

        return $effect;
    }

    public function getUserNotification(ItemEffect $effect, ?User $target): ?array
    {
        return [
            'title' => 'Hazmat Suit',
            'body' => 'All negative effects removed!',
        ];
    }
}
