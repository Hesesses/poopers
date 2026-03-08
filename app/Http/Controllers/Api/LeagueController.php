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
        $this->authorizeLeagueMember($request->user(), $league);

        return new LeagueResource($league->load('members'));
    }

    public function update(Request $request, League $league): LeagueResource
    {
        $this->authorizeLeagueAdmin($request->user(), $league);

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
        $this->authorizeLeagueAdmin($request->user(), $league);

        $league->delete();

        return response()->json(['message' => 'League deleted.']);
    }

    private function authorizeLeagueMember(\App\Models\User $user, League $league): void
    {
        if (! $league->members()->where('user_id', $user->id)->exists()) {
            abort(403, 'You are not a member of this league.');
        }
    }

    private function authorizeLeagueAdmin(\App\Models\User $user, League $league): void
    {
        $member = $league->leagueMembers()->where('user_id', $user->id)->first();
        if (! $member || ! $member->isAdmin()) {
            abort(403, 'Only league admins can perform this action.');
        }
    }
}
