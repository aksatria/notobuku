<?php

namespace App\Http\Controllers;

use App\Support\OpacMetrics;

class OpacMetricsController extends Controller
{
    public function __invoke(\Illuminate\Http\Request $request)
    {
        return response()->json([
            'ok' => true,
            'trace_id' => (string) ($request->attributes->get('trace_id') ?: $request->header('X-Trace-Id', '')),
            'metrics' => OpacMetrics::snapshot(),
        ]);
    }
}
