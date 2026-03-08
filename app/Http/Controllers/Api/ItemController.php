<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserItemResource;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function __construct(
        private ItemService $itemService,
    ) {}

    public function index(Request $request, League $league): JsonResponse
    {
        $this->authorize('useItems', $league);

        $items = UserItem::query()
            ->where('user_id', $request->user()->id)
            ->where('league_id', $league->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('item')
            ->get();

        return response()->json(UserItemResource::collection($items));
    }

    public function use(Request $request, League $league, string $itemId): JsonResponse
    {
        $this->authorize('useItems', $league);

        $validated = $request->validate([
            'target_user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $userItem = UserItem::query()
            ->where('id', $itemId)
            ->where('user_id', $request->user()->id)
            ->where('league_id', $league->id)
            ->with('item')
            ->firstOrFail();

        $target = User::query()->findOrFail($validated['target_user_id']);

        $effect = $this->itemService->useItem($userItem, $request->user(), $target, $league);

        $response = [
            'message' => 'Item used successfully.',
            'effect_status' => $effect->status->name,
        ];

        // For spy items, include the spy data
        if ($userItem->item->type === ItemType::Strategic) {
            $spyData = $this->itemService->handleSpyItem($userItem, $target, $league);
            if ($spyData) {
                $response['spy_data'] = $spyData;
            }
        }

        return response()->json($response);
    }
}
