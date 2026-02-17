<?php

namespace App\Http\Controllers;

use App\Support\CirculationMetrics;
use Illuminate\Http\Request;

class CirculationMetricsController extends Controller
{
    public function __invoke(Request $request)
    {
        return response()->json([
            'ok' => true,
            'metrics' => CirculationMetrics::snapshot(),
        ]);
    }
}

