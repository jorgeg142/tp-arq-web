<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    public function index()
    {
        $data = $this->service->summary(6); // mismos 6 meses que tu versión
        return view('dashboard', $data);
    }
}
