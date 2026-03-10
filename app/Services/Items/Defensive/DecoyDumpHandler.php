<?php

namespace App\Services\Items\Defensive;

use App\Enums\ItemEffectStatus;
use App\Enums\ItemType;
use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Items\BaseItemHandler;
use Illuminate\Validation\ValidationException;

class DecoyDumpHandler extends BaseItemHandler
{
    private bool $success = false;

    public function requiresTarget(): bool
    {
        return false;
    }

    public function allowsSelfTarget(): bool
    {
        return true;
    }

    public function validate(UserItem $userItem, User $user, ?User $target, League $league): void
    {
        $effectId = request()->input('effect_id');

        if (! $effectId) {
            throw ValidationException::withMessages([
                'effect_id' => ['You must select an effect to counter.'],
            ]);
        }

        $effect = ItemEffect::query()
            ->where('id', $effectId)
            ->where('target_user_id', $user->id)
            ->where('status', ItemEffectStatus::Applied)
            ->where('date', now()->toDateString())
            ->whereHas('userItem.item', fn ($q) => $q->where('type', ItemType::Offensive))
            ->first();

        if (! $effect) {
            throw ValidationException::withMessages([
                'effect_id' => ['The selected effect does not exist or cannot be countered.'],
            ]);
        }
    }

    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect
    {
        $effectId = request()->input('effect_id');

        $targetEffect = ItemEffect::query()
            ->where('id', $effectId)
            ->where('target_user_id', $user->id)
            ->where('status', ItemEffectStatus::Applied)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        $this->success = (bool) random_int(0, 1);

        if ($this->success) {
            $targetEffect->update(['status' => ItemEffectStatus::Cancelled]);
            $this->recalculateSteps($user);
        }

        return $this->createEffect($userItem, $user, $league);
    }

    public function getResponseData(ItemEffect $effect): ?array
    {
        return ['success' => $this->success];
    }

    public function getUserNotification(ItemEffect $effect, ?User $target): ?array
    {
        return [
            'title' => 'Decoy Dump',
            'body' => $this->success
                ? 'Decoy worked! The effect was cancelled.'
                : 'Decoy failed! The effect remains.',
        ];
    }
}
