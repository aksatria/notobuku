<?php

namespace App\Http\Controllers;

use App\Support\ReservationMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationMetricsController extends Controller
{
    public function __invoke(Request $request)
    {
        $institutionId = (int) (Auth::user()->active_institution_id
            ?? Auth::user()->active_inst_id
            ?? Auth::user()->institution_id
            ?? 1);

        $windowDays = (int) $request->query('days', (int) config('notobuku.reservations.kpi.window_days', 30));

        return response()->json([
            'ok' => true,
            'metrics' => ReservationMetrics::snapshot($institutionId > 0 ? $institutionId : 1, $windowDays),
        ]);
    }
}
