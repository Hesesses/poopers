<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DraftResource;
use App\Models\Draft;
use App\Models\Item;
use App\Models\League;
use App\Services\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    public function __construct(
        private DraftService $draftService,
    ) {}

    public function index(Request $request, League $league): JsonResponse
    {
        $this->authorize('viewDrafts', $league);

        $drafts = Draft::query()
            ->where('league_id', $league->id)
            ->with('picks.user', 'picks.item')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json(DraftResource::collection($drafts));
    }

    public function show(Request $request, League $league, Draft $draft): DraftResource
    {
        $this->authorize('viewDrafts', $league);

        $draft->load('picks.user', 'picks.item');

        // Include available items
        $availableItemIds = array_diff(
            $draft->available_items,
            $draft->picks->pluck('item_id')->toArray(),
        );

        $draft->setAttribute('remaining_items', Item::query()->whereIn('id', $availableItemIds)->get());

        return new DraftResource($draft);
    }

    public function pick(Request $request, League $league, Draft $draft): JsonResponse
    {
        $this->authorize('viewDrafts', $league);

        $validated = $request->validate([
            'item_id' => ['required', 'uuid', 'exists:items,id'],
        ]);

        $item = Item::query()->findOrFail($validated['item_id']);

        $pick = $this->draftService->pick($draft, $request->user(), $item);

        return response()->json([
            'message' => 'Item picked successfully.',
            'pick' => [
                'item' => $pick->item->name,
                'pick_number' => $pick->pick_number,
            ],
        ]);
    }
}
