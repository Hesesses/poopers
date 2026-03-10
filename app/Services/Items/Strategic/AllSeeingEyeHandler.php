<?php

namespace App\Services\Items\Strategic;

use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;

class AllSeeingEyeHandler extends BaseItemHandler
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

    public function getUserNotification(ItemEffect $effect, ?User $target): ?array
    {
        return [
            'title' => 'All Seeing Eye',
            'body' => "👁️ All Seeing Eye activated! You can see everyone's steps today.",
        ];
    }
}
