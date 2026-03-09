<?php

namespace App\Services\Items\Strategic;

use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class ToiletSwapHandler extends BaseItemHandler
{
    public function allowsSelfTarget(): bool
    {
        return false;
    }

    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league);

        $stolenItem = UserItem::query()
            ->where('user_id', $target->id)
            ->where('league_id', $league->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('id', '!=', $userItem->id)
            ->inRandomOrder()
            ->first();

        if ($stolenItem) {
            $stolenItem->update(['user_id' => $user->id]);
        }

        return $effect;
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Toilet Swap!',
            'body' => 'An item was stolen from your inventory!',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseData(ItemEffect $effect): ?array
    {
        $stolenItem = UserItem::query()
            ->where('user_id', $effect->target_user_id)
            ->where('league_id', $effect->league_id)
            ->latest('updated_at')
            ->with('item')
            ->first();

        if (! $stolenItem) {
            return ['stolen_item' => null];
        }

        return [
            'stolen_item' => [
                'id' => $stolenItem->id,
                'item_name' => $stolenItem->item->name,
                'item_slug' => $stolenItem->item->slug,
            ],
        ];
    }
}
