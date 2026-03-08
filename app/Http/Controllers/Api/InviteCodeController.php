<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Services\LeagueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteCodeController extends Controller
{
    public function __construct(
        private LeagueService $leagueService,
    ) {}

    public function show(Request $request, League $league): JsonResponse
    {
        if (! $league->members()->where('user_id', $request->user()->id)->exists()) {
            abort(403, 'You are not a member of this league.');
        }

        return response()->json(['invite_code' => $league->invite_code]);
    }

    public function refresh(Request $request, League $league): JsonResponse
    {
        $member = $league->leagueMembers()->where('user_id', $request->user()->id)->first();
        if (! $member || ! $member->isAdmin()) {
            abort(403, 'Only league admins can refresh the invite code.');
        }

        $code = $this->leagueService->refreshInviteCode($league);

        return response()->json(['invite_code' => $code]);
    }
}
