<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ActiveInstitutionAccess;
use App\Support\ReservationMetrics;
use Illuminate\Http\Request;

class ReservationMetricsController extends Controller
{
    use ActiveInstitutionAccess;

    public function __invoke(Request $request)
    {
        $windowDays = (int) $request->query('days', (int) config('notobuku.reservations.kpi.window_days', 30));

        return response()->json([
            'ok' => true,
            'metrics' => ReservationMetrics::snapshot($this->currentInstitutionId(), $windowDays),
        ]);
    }
}
