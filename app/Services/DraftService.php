<?php

namespace App\Services;

use App\Enums\DraftStatus;
use App\Enums\DraftType;
use App\Enums\ItemSource;
use App\Models\Draft;
use App\Models\DraftPick;
use App\Models\Item;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use Illuminate\Validation\ValidationException;

class DraftService
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function createDraft(
        League $league,
        DraftType $type = DraftType::Weekly,
        ?string $name = null,
    ): Draft {
        $memberCount = $league->memberCount();

        $standings = $this->getRecentStandings($league);
        $pickOrder = $standings->pluck('user_id')->toArray();

        $items = $this->generateDraftItems($memberCount);

        $draft = Draft::query()->create([
            'league_id' => $league->id,
            'type' => $type,
            'name' => $name,
            'date' => now()->toDateString(),
            'available_items' => $items,
            'pick_order' => $pickOrder,
            'status' => DraftStatus::InProgress,
            'expires_at' => now()->addHours(24),
        ]);

        // Notify first picker
        $this->notifyNextPicker($draft);

        return $draft;
    }

    public function pick(Draft $draft, User $user, Item $item): DraftPick
    {
        if ($draft->isComplete()) {
            throw ValidationException::withMessages([
                'draft' => 'This draft is already complete.',
            ]);
        }

        $currentUserId = $draft->currentPickerUserId();
        if ($currentUserId !== $user->id) {
            throw ValidationException::withMessages([
                'draft' => 'It is not your turn to pick.',
            ]);
        }

        // Validate item is available
        if (! in_array($item->id, $draft->available_items)) {
            throw ValidationException::withMessages([
                'item' => 'This item is not available in this draft.',
            ]);
        }

        // Check item hasn't been picked already
        if ($draft->picks()->where('item_id', $item->id)->exists()) {
            throw ValidationException::withMessages([
                'item' => 'This item has already been picked.',
            ]);
        }

        $pick = DraftPick::query()->create([
            'draft_id' => $draft->id,
            'user_id' => $user->id,
            'item_id' => $item->id,
            'pick_number' => $draft->current_pick_index + 1,
            'picked_at' => now(),
        ]);

        // Give item to user
        UserItem::query()->create([
            'user_id' => $user->id,
            'league_id' => $draft->league_id,
            'item_id' => $item->id,
            'source' => ItemSource::Draft,
            'expires_at' => now()->addDays(7),
        ]);

        // Advance draft
        $draft->increment('current_pick_index');
        $draft->refresh();

        if ($draft->current_pick_index >= count($draft->pick_order)) {
            $draft->update(['status' => DraftStatus::Completed]);
        } else {
            $this->notifyNextPicker($draft);
        }

        return $pick;
    }

    public function autoAssignPick(Draft $draft): ?DraftPick
    {
        $currentUserId = $draft->currentPickerUserId();
        if (! $currentUserId) {
            return null;
        }

        $user = User::query()->find($currentUserId);
        if (! $user) {
            return null;
        }

        // Get remaining items
        $pickedItemIds = $draft->picks()->pluck('item_id')->toArray();
        $remainingItemIds = array_diff($draft->available_items, $pickedItemIds);

        if (empty($remainingItemIds)) {
            return null;
        }

        $randomItemId = $remainingItemIds[array_rand($remainingItemIds)];
        $item = Item::query()->find($randomItemId);

        if (! $item) {
            return null;
        }

        return $this->pick($draft, $user, $item);
    }

    /**
     * @return array<string>
     */
    private function generateDraftItems(int $count): array
    {
        return Item::query()
            ->inRandomOrder()
            ->limit($count)
            ->pluck('id')
            ->toArray();
    }

    private function getRecentStandings(League $league): \Illuminate\Support\Collection
    {
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();

        $results = \App\Models\LeagueDayResult::query()
            ->where('league_id', $league->id)
            ->whereBetween('date', [$lastWeekStart, $lastWeekEnd])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($results, $userId) => (object) [
                'user_id' => $userId,
                'wins' => $results->where('is_winner', true)->count(),
            ])
            ->sortByDesc('wins')
            ->values();

        // Include members with no results at the end
        $allMembers = $league->members()->pluck('users.id');
        $resultUserIds = $results->pluck('user_id');
        $missing = $allMembers->diff($resultUserIds)->map(fn ($id) => (object) [
            'user_id' => $id,
            'wins' => 0,
        ]);

        return $results->concat($missing)->values();
    }

    private function notifyNextPicker(Draft $draft): void
    {
        $nextUserId = $draft->currentPickerUserId();
        if (! $nextUserId) {
            return;
        }

        $user = User::query()->find($nextUserId);
        if (! $user) {
            return;
        }

        $this->notificationService->create(
            $user,
            $draft->league,
            'draft_turn',
            'Your turn to pick!',
            "It's your turn in the {$draft->league->name} draft. You have 4 hours to pick.",
        );
    }
}
