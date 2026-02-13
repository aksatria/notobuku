<?php

namespace App\Http\Controllers;

use App\Support\InteropMetrics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InteropMetricsController extends Controller
{
    public function __invoke(Request $request)
    {
        return response()->json([
            'ok' => true,
            'metrics' => InteropMetrics::snapshot(),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $days = (int) $request->query('days', 30);
        $days = max(1, min(90, $days));
        $rows = InteropMetrics::dailySummary($days);
        $filename = 'interop-metrics-daily-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'day',
                'oai_p95_ms',
                'sru_p95_ms',
                'p95_ms',
                'oai_invalid_token',
                'sru_invalid_token',
                'invalid_token_total',
                'oai_rate_limited',
                'sru_rate_limited',
                'rate_limited_total',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['day'] ?? '',
                    (int) ($row['oai_p95_ms'] ?? 0),
                    (int) ($row['sru_p95_ms'] ?? 0),
                    (int) ($row['p95_ms'] ?? 0),
                    (int) ($row['oai_invalid_token'] ?? 0),
                    (int) ($row['sru_invalid_token'] ?? 0),
                    (int) ($row['invalid_token_total'] ?? 0),
                    (int) ($row['oai_rate_limited'] ?? 0),
                    (int) ($row['sru_rate_limited'] ?? 0),
                    (int) ($row['rate_limited_total'] ?? 0),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
