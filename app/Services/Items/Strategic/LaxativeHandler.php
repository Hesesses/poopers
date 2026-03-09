<?php

namespace App\Services\Items\Strategic;

use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;
use App\Services\NotificationService;

class LaxativeHandler extends BaseItemHandler
{
    public function allowsSelfTarget(): bool
    {
        return false;
    }

    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effect = $this->createEffect($userItem, $target, $league);
        $steps = $this->getUserSteps($target);
        $notificationService = app(NotificationService::class);

        $league->loadMissing('members');

        foreach ($league->members as $member) {
            if ($member->id === $user->id) {
                continue;
            }

            $notificationService->create(
                $member,
                $league,
                'force_reveal',
                'Step Reveal!',
                "{$target->full_name} has {$steps} steps today!",
            );
        }

        return $effect;
    }
}
