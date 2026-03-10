<?php

namespace App\Services\Items;

use App\Enums\ItemEffectStatus;
use App\Models\DailySteps;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\Contracts\ItemHandlerInterface;
use App\Services\StepSyncService;

abstract class BaseItemHandler implements ItemHandlerInterface
{
    public function requiresTarget(): bool
    {
        return true;
    }

    public function allowsSelfTarget(): bool
    {
        return false;
    }

    public function validate(UserItem $userItem, User $user, ?User $target, League $league): void {}

    public function getResponseData(ItemEffect $effect): ?array
    {
        return null;
    }

    public function hasMidnightResolution(): bool
    {
        return false;
    }

    public function resolveAtMidnight(ItemEffect $effect, League $league): void {}

    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array
    {
        return null;
    }

    public function getUserNotification(ItemEffect $effect, ?User $target): ?array
    {
        return null;
    }

    protected function createEffect(
        UserItem $userItem,
        User $target,
        League $league,
        ItemEffectStatus $status = ItemEffectStatus::Applied,
    ): ItemEffect {
        return ItemEffect::query()->create([
            'user_item_id' => $userItem->id,
            'target_user_id' => $target->id,
            'league_id' => $league->id,
            'date' => now()->toDateString(),
            'status' => $status,
        ]);
    }

    protected function recalculateSteps(User $user, ?string $date = null): void
    {
        app(StepSyncService::class)->recalculateModifiedSteps($user, $date ?? now()->toDateString());
    }

    protected function getUserSteps(User $user, ?string $date = null): int
    {
        $date ??= now()->toDateString();

        return DailySteps::query()
            ->where('user_id', $user->id)
            ->where('date', $date)
            ->value('steps') ?? 0;
    }

    protected function getUserModifiedSteps(User $user, ?string $date = null): int
    {
        $date ??= now()->toDateString();

        return DailySteps::query()
            ->where('user_id', $user->id)
            ->where('date', $date)
            ->value('modified_steps') ?? 0;
    }
}
