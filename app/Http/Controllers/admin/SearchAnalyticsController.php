<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\OpacMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchAnalyticsController extends Controller
{
    private function institutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index(Request $request)
    {
        $institutionId = $this->institutionId();
        $days = max(7, min(90, (int) $request->query('days', 30)));

        $snapshot = OpacMetrics::snapshot();
        $searchAnalytics = (array) ($snapshot['search_analytics'] ?? []);

        $zeroQueue = collect();
        if (Schema::hasTable('search_queries')) {
            $zeroQueue = DB::table('search_queries')
                ->where('institution_id', $institutionId)
                ->where('last_hits', '<=', 0)
                ->selectRaw('zero_result_status as status, COUNT(*) as total')
                ->groupBy('zero_result_status')
                ->pluck('total', 'status');
        }

        $synonymStats = collect();
        if (Schema::hasTable('search_synonyms') && Schema::hasColumn('search_synonyms', 'status')) {
            $synonymStats = DB::table('search_synonyms')
                ->where('institution_id', $institutionId)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');
        }

        $trend = collect();
        if (Schema::hasTable('search_query_events')) {
            $trend = DB::table('search_query_events')
                ->where('institution_id', $institutionId)
                ->where('searched_at', '>=', now()->subDays($days))
                ->selectRaw('DATE(searched_at) as d, COUNT(*) as total, SUM(CASE WHEN is_zero_result = 1 THEN 1 ELSE 0 END) as zero_total')
                ->groupBy('d')
                ->orderBy('d')
                ->get();
        }

        $trendLabels = $trend->pluck('d')->map(fn ($d) => (string) $d)->values();
        $trendTotal = $trend->pluck('total')->map(fn ($n) => (int) $n)->values();
        $trendZero = $trend->pluck('zero_total')->map(fn ($n) => (int) $n)->values();

        return view('admin.search-analytics', [
            'days' => $days,
            'searchAnalytics' => $searchAnalytics,
            'zeroQueue' => $zeroQueue,
            'synonymStats' => $synonymStats,
            'trendLabels' => $trendLabels,
            'trendTotal' => $trendTotal,
            'trendZero' => $trendZero,
        ]);
    }
}

