<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemEffectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UseItemRequest;
use App\Http\Resources\UserItemResource;
use App\Models\ItemEffect;
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

    public function use(UseItemRequest $request, League $league, string $itemId): JsonResponse
    {
        $this->authorize('useItems', $league);

        $userItem = UserItem::query()
            ->where('id', $itemId)
            ->where('user_id', $request->user()->id)
            ->where('league_id', $league->id)
            ->with('item')
            ->firstOrFail();

        $target = null;
        if ($request->validated('target_user_id')) {
            $target = User::query()->findOrFail($request->validated('target_user_id'));
        }

        $result = $this->itemService->useItem($userItem, $request->user(), $target, $league);

        $response = [
            'message' => 'Item used successfully.',
            'effect_status' => $result['effect']->status->name,
        ];

        if ($result['response_data']) {
            $response['response_data'] = $result['response_data'];
        }

        return response()->json($response);
    }

    public function activeEffects(Request $request, League $league): JsonResponse
    {
        $this->authorize('useItems', $league);

        $effects = ItemEffect::query()
            ->where('target_user_id', $request->user()->id)
            ->where('league_id', $league->id)
            ->where('date', now()->toDateString())
            ->where('status', ItemEffectStatus::Applied)
            ->with('userItem.item', 'userItem.user')
            ->get();

        $data = $effects->map(fn (ItemEffect $effect) => [
            'id' => $effect->id,
            'item_name' => $effect->userItem->item->name,
            'item_icon' => $effect->userItem->item->icon,
            'item_description' => $effect->userItem->item->description,
            'item_type' => $effect->userItem->item->type->value,
            'attacker_name' => $effect->userItem->user->full_name,
        ]);

        return response()->json(['effects' => $data]);
    }
}
