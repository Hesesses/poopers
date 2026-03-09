<?php

namespace App\Services\Items\Offensive;

use App\Enums\ItemEffectStatus;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;
use App\Services\Items\DefenseResolver;

class ExplosiveDiarrheaHandler extends BaseItemHandler
{
    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league, ItemEffectStatus::Pending);

        $defense = app(DefenseResolver::class)->resolve($effect, $userItem, $target, $league);

        if ($defense->blocked || $defense->reflected || $defense->missed) {
            return $effect->fresh();
        }

        $effect->update(['status' => ItemEffectStatus::Applied]);
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

        return $effect->fresh();
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Explosive Diarrhea!',
            'body' => 'Explosive Diarrhea! -10% steps and splash damage!',
        ];
    }
}
