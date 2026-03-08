<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Services\StandingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    public function __construct(
        private StandingsService $standingsService,
    ) {}

    public function month(Request $request, League $league): JsonResponse
    {
        $this->authorizeLeagueMember($request->user(), $league);

        $standings = $this->standingsService->getMonthStandings($league);

        return response()->json(['standings' => $standings]);
    }

    public function week(Request $request, League $league): JsonResponse
    {
        $this->authorizeLeagueMember($request->user(), $league);

        $standings = $this->standingsService->getWeekStandings($league);

        return response()->json(['standings' => $standings]);
    }

    public function yesterday(Request $request, League $league): JsonResponse
    {
        $this->authorizeLeagueMember($request->user(), $league);

        $results = $this->standingsService->getYesterdayResults($league);

        return response()->json(['results' => $results]);
    }

    public function today(Request $request, League $league): JsonResponse
    {
        $this->authorizeLeagueMember($request->user(), $league);

        $data = $this->standingsService->getToday($league, $request->user());

        return response()->json($data);
    }

    private function authorizeLeagueMember(\App\Models\User $user, League $league): void
    {
        if (! $league->members()->where('user_id', $user->id)->exists()) {
            abort(403, 'You are not a member of this league.');
        }
    }
}
