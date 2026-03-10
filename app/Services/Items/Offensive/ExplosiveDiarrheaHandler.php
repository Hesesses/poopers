<?php

namespace App\Services\Items\Offensive;

use App\Enums\ItemEffectStatus;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class ExplosiveDiarrheaHandler extends BaseItemHandler
{
    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league);
        $this->recalculateSteps($target);

        $league->loadMissing('members');
        $otherMembers = $league->members
            ->where('id', '!=', $user->id)
            ->where('id', '!=', $target->id);

        if ($otherMembers->isNotEmpty()) {
            $splashTarget = $otherMembers->random();

            ItemEffect::query()->create([
                'user_item_id' => $userItem->id,
                'target_user_id' => $splashTarget->id,
                'league_id' => $league->id,
                'date' => now()->toDateString(),
                'status' => ItemEffectStatus::Applied,
            ]);
            $this->recalculateSteps($splashTarget);
        }

        return $effect;
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Explosive Diarrhea!',
            'body' => 'Explosive Diarrhea! -10% steps and splash damage!',
        ];
    }
}
