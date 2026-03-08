<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeagueResource;
use App\Models\League;
use App\Services\LeagueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    public function __construct(
        private LeagueService $leagueService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $leagues = $request->user()->leagues()->with('members')->get();

        return response()->json(LeagueResource::collection($leagues));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
        ]);

        $league = $this->leagueService->create(
            $request->user(),
            $validated['name'],
            $validated['icon'] ?? '💩',
            $validated['timezone'] ?? 'UTC',
        );

        return response()->json(new LeagueResource($league->load('members')), 201);
    }

    public function show(Request $request, League $league): LeagueResource
    {
        $this->authorize('view', $league);

        return new LeagueResource($league->load('members'));
    }

    public function update(Request $request, League $league): LeagueResource
    {
        $this->authorize('update', $league);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'icon' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
        ]);

        $league->update($validated);

        return new LeagueResource($league->fresh()->load('members'));
    }

    public function destroy(Request $request, League $league): JsonResponse
    {
        $this->authorize('delete', $league);

        $league->delete();

        return response()->json(['message' => 'League deleted.']);
    }
}
