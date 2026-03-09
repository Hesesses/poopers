<?php

namespace App\Services\Items\Offensive;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemType;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class CourtesyFlushHandler extends BaseItemHandler
{
    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league, ItemEffectStatus::Applied);

        $today = now()->toDateString();

        ItemEffect::query()
            ->whereHas('userItem', function ($q) use ($target, $league) {
                $q->where('user_id', $target->id)
                    ->where('league_id', $league->id)
                    ->whereHas('item', fn ($iq) => $iq->where('type', ItemType::Defensive));
            })
            ->where('date', $today)
            ->where('status', ItemEffectStatus::Applied)
            ->update(['status' => ItemEffectStatus::Cancelled]);

        return $effect->fresh();
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Courtesy Flush!',
            'body' => 'Your defenses have been removed!',
        ];
    }
}
