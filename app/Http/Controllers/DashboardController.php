<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StatsFilterRequest;
use App\Services\Stats\StatsAggregator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly StatsAggregator $stats,
    ) {}

    public function index(): View
    {
        return view('dashboard', [
            'defaultFrom' => now()->subDays(30)->toDateString(),
            'defaultTo'   => now()->toDateString(),
        ]);
    }

    public function requestsChart(StatsFilterRequest $request): JsonResponse
    {
        return response()->json($this->stats->requestsByDay($request->filters()));
    }

    public function browsersChart(StatsFilterRequest $request): JsonResponse
    {
        return response()->json($this->stats->browserShareByDay($request->filters()));
    }

    public function table(StatsFilterRequest $request): JsonResponse
    {
        $filters = $request->filters();
        return response()->json([
            'rows' => $this->stats->dailyTable($filters),
            'sort' => $filters['sort'],
            'dir'  => $filters['dir'],
        ]);
    }
}
