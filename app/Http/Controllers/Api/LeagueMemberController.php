<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeagueMemberResource;
use App\Http\Resources\LeagueResource;
use App\Models\League;
use App\Models\User;
use App\Services\LeagueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueMemberController extends Controller
{
    public function __construct(
        private LeagueService $leagueService,
    ) {}

    public function joinByCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $league = League::query()
            ->where('invite_code', $validated['invite_code'])
            ->firstOrFail();

        $this->leagueService->join($request->user(), $league, $validated['invite_code']);

        return response()->json(new LeagueResource($league->load('members')));
    }

    public function index(Request $request, League $league): JsonResponse
    {
        $members = $league->leagueMembers()->with('user')->get();

        return response()->json(LeagueMemberResource::collection($members));
    }

    public function join(Request $request, League $league): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $this->leagueService->join($request->user(), $league, $validated['invite_code']);

        return response()->json(['message' => 'Joined league successfully.']);
    }

    public function leave(Request $request, League $league): JsonResponse
    {
        $this->leagueService->leave($request->user(), $league);

        return response()->json(['message' => 'Left league successfully.']);
    }

    public function remove(Request $request, League $league, User $user): JsonResponse
    {
        $member = $league->leagueMembers()->where('user_id', $request->user()->id)->first();
        if (! $member || ! $member->isAdmin()) {
            abort(403, 'Only league admins can remove members.');
        }

        $this->leagueService->removeMember($request->user(), $league, $user);

        return response()->json(['message' => 'Member removed.']);
    }
}
