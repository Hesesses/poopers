<?php

namespace App\Services\Items\Strategic;

use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;
use App\Services\Items\ItemHandlerRegistry;

class PruneJuiceHandler extends BaseItemHandler
{
    public function allowsSelfTarget(): bool
    {
        return false;
    }

    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league);

        $targetItem = UserItem::query()
            ->where('user_id', $target->id)
            ->where('league_id', $league->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('item')
            ->get()
            ->sortByDesc(fn ($ui) => $ui->item->rarity->value)
            ->first();

        if (! $targetItem) {
            return $effect;
        }

        $league->loadMissing('members');
        $randomMember = $league->members->where('id', '!=', $target->id)->random();

        if ($randomMember) {
            $targetItem->update([
                'used_at' => now(),
                'used_on_user_id' => $randomMember->id,
            ]);

            $handler = app(ItemHandlerRegistry::class)->resolve($targetItem->item);
            $handler->execute($targetItem, $target, $randomMember, $league);
        }

        return $effect;
    }
}
