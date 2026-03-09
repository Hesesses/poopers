<?php

namespace App\Services\Items\Strategic;

use App\Enums\ItemEffectStatus;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;
use App\Services\Items\ItemHandlerRegistry;

class CopycatHandler extends BaseItemHandler
{
    public function allowsSelfTarget(): bool
    {
        return false;
    }

    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $today = now()->toDateString();

        $lastEffect = ItemEffect::query()
            ->where('league_id', $league->id)
            ->where('date', $today)
            ->where('status', ItemEffectStatus::Applied)
            ->where('user_item_id', '!=', $userItem->id)
            ->with('userItem.item')
            ->latest()
            ->first();

        if (! $lastEffect) {
            return $this->createEffect($userItem, $target, $league);
        }

        $handler = app(ItemHandlerRegistry::class)->resolve($lastEffect->userItem->item);

        return $handler->execute($userItem, $user, $target, $league);
    }
}
