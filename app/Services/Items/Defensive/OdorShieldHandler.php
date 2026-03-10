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

class OdorShieldHandler extends BaseItemHandler
{
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
                'effect_id' => ['You must select an effect to remove.'],
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
                'effect_id' => ['The selected effect does not exist or cannot be removed.'],
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
            ->with('userItem')
            ->firstOrFail();

        $attacker = User::findOrFail($targetEffect->userItem->user_id);

        $targetEffect->update(['status' => ItemEffectStatus::Cancelled]);

        $effect = $this->createEffect($userItem, $user, $league);

        // Create hide-steps effect targeting the attacker
        ItemEffect::query()->create([
            'user_item_id' => $userItem->id,
            'target_user_id' => $attacker->id,
            'league_id' => $league->id,
            'date' => now()->toDateString(),
            'status' => ItemEffectStatus::Applied,
        ]);

        $this->recalculateSteps($user);

        return $effect;
    }

    public function getUserNotification(ItemEffect $effect, ?User $target): ?array
    {
        return [
            'title' => 'Odor Shield',
            'body' => 'Effect removed and your steps are hidden from the attacker!',
        ];
    }
}
