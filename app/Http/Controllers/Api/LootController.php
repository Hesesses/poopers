<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemSource;
use App\Exceptions\InventoryFullException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserItemResource;
use App\Models\League;
use App\Models\UserItem;
use App\Services\ItemService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LootController extends Controller
{
    public function __construct(
        private ItemService $itemService,
        private NotificationService $notificationService,
    ) {}

    public function claim(Request $request, League $league): JsonResponse
    {
        $this->authorize('useItems', $league);

        $user = $request->user();
        $leagueTime = now()->setTimezone($league->timezone);
        $hour = $leagueTime->hour;
        $today = $leagueTime->copy()->startOfDay();

        if ($hour < 8) {
            return response()->json(['message' => 'Loot is not available yet.'], 422);
        }

        $alreadyClaimed = UserItem::query()
            ->where('user_id', $user->id)
            ->where('league_id', $league->id)
            ->where('source', ItemSource::Loot)
            ->where('created_at', '>=', $today->copy()->setTimezone('UTC'))
            ->exists();

        if ($alreadyClaimed) {
            return response()->json(['message' => 'Already claimed today.'], 422);
        }

        try {
            $userItem = $this->itemService->awardRandomItem($user, $league, ItemSource::Loot);
            $userItem->load('item');

            $this->notificationService->create(
                $user,
                $league,
                'item_received',
                "Daily Loot! [{$league->name}]",
                "You found a {$userItem->item->name}!",
                sendPush: false,
            );

            return response()->json([
                'user_item' => new UserItemResource($userItem),
                'message' => 'Item claimed!',
            ]);
        } catch (InventoryFullException) {
            return response()->json([
                'message' => 'Inventory full.',
                'is_pro' => $user->isPro(),
            ], 422);
        }
    }
}
