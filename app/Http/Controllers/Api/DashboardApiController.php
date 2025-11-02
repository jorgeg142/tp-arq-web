<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DashboardService;

class DashboardApiController extends Controller
{
    public function __construct(private DashboardService $service) {}

    // GET /api/dashboard?months=6
    public function summary(Request $request)
    {
        $months = max(1, (int) $request->query('months', 6));
        $data   = $this->service->summary($months);

        return response()->json([
            'ok'   => true,
            'kpis' => $data['kpis'],
            'trend' => $data['trend'],
            'distribution' => $data['distribution'],
            'activity' => $data['activity'],
        ]);
    }
}
