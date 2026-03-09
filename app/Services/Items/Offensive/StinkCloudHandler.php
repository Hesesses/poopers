<?php

namespace App\Services\Items\Offensive;

use App\Enums\ItemEffectStatus;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;
use App\Services\Items\DefenseResolver;

class StinkCloudHandler extends BaseItemHandler
{
    public function requiresTarget(): bool
    {
        return false;
    }

    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $league->loadMissing('members');
        $members = $league->members->where('id', '!=', $user->id);

        $firstEffect = null;

        foreach ($members as $member) {
            $effect = $this->createEffect($userItem, $member, $league, ItemEffectStatus::Pending);

            $defense = app(DefenseResolver::class)->resolve($effect, $userItem, $member, $league);

            if (! $defense->blocked && ! $defense->reflected && ! $defense->missed) {
                $effect->update(['status' => ItemEffectStatus::Applied]);
                $this->recalculateSteps($member);
            }

            $firstEffect ??= $effect;
        }

        return $firstEffect->fresh();
    }

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return [
            'title' => 'Stink Cloud!',
            'body' => 'A Stink Cloud hit your league! -2% steps for everyone.',
        ];
    }
}
