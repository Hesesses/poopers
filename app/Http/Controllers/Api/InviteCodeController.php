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
        $this->authorize('viewInviteCode', $league);

        return response()->json(['invite_code' => $league->invite_code]);
    }

    public function refresh(Request $request, League $league): JsonResponse
    {
        $this->authorize('refreshInviteCode', $league);

        $code = $this->leagueService->refreshInviteCode($league);

        return response()->json(['invite_code' => $code]);
    }
}
