<?php

namespace App\Services\Items\Strategic;

use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class TheInsiderHandler extends BaseItemHandler
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
        return $this->createEffect($userItem, $user, $league);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseData(ItemEffect $effect): ?array
    {
        $inventory = UserItem::query()
            ->where('league_id', $effect->league_id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with(['item', 'user'])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($items) => $items->map(fn ($ui) => [
                'id' => $ui->id,
                'item_name' => $ui->item->name,
                'item_slug' => $ui->item->slug,
                'item_rarity' => $ui->item->rarity->name,
                'item_icon' => $ui->item->icon,
            ]))
            ->toArray();

        return ['inventory' => $inventory];
    }
}
