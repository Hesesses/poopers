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
        $this->authorize('viewStandings', $league);

        $standings = $this->standingsService->getMonthStandings($league);

        return response()->json(['standings' => $standings]);
    }

    public function week(Request $request, League $league): JsonResponse
    {
        $this->authorize('viewStandings', $league);

        $standings = $this->standingsService->getWeekStandings($league);

        return response()->json(['standings' => $standings]);
    }

    public function yesterday(Request $request, League $league): JsonResponse
    {
        $this->authorize('viewStandings', $league);

        $leagueHour = now()->setTimezone($league->timezone)->hour;

        if ($leagueHour < 8) {
            return response()->json([
                'results' => [],
                'announced' => false,
            ]);
        }

        $results = $this->standingsService->getYesterdayResults($league);

        return response()->json([
            'results' => $results,
            'announced' => true,
        ]);
    }

    public function today(Request $request, League $league): JsonResponse
    {
        $this->authorize('viewStandings', $league);

        $data = $this->standingsService->getToday($league, $request->user());

        return response()->json($data);
    }
}
