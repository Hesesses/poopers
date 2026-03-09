<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UseItemRequest;
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
}
