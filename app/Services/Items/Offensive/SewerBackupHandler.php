<?php

namespace App\Services\Items\Offensive;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemEffectType;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;
use App\Services\Items\DefenseResolver;

class SewerBackupHandler extends BaseItemHandler
{
    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league, ItemEffectStatus::Pending);

        $defense = app(DefenseResolver::class)->resolve($effect, $userItem, $target, $league);

        if ($defense->blocked || $defense->reflected || $defense->missed) {
            return $effect->fresh();
        }

        $today = now()->toDateString();

        $boostEffects = ItemEffect::query()
            ->whereHas('userItem', function ($q) use ($target, $league) {
                $q->where('user_id', $target->id)
                    ->where('league_id', $league->id)
                    ->whereHas('item', fn ($iq) => $iq->where('effect->type', ItemEffectType::BoostSteps->value));
            })
            ->where('date', $today)
            ->where('status', ItemEffectStatus::Applied)
            ->get();

        foreach ($boostEffects as $boostEffect) {
            ItemEffect::query()->create([
                'user_item_id' => $userItem->id,
                'target_user_id' => $target->id,
                'league_id' => $league->id,
                'date' => $today,
                'status' => ItemEffectStatus::Applied,
            ]);
        }

        $effect->update(['status' => ItemEffectStatus::Applied]);
        $this->recalculateSteps($target);

        return $effect->fresh();
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Sewer Backup!',
            'body' => 'Your boosts have been reversed!',
        ];
    }
}
