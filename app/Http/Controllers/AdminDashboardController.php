<?php

namespace App\Http\Controllers;

use App\Models\Biblio;
use App\Support\InteropMetrics;
use App\Support\OpacMetrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    private function currentInstitutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $institutionId = $this->currentInstitutionId();
        $branchId = (int) $request->query('branch_id', 0);
        $branchId = $branchId > 0 ? $branchId : null;
        $range = (int) $request->query('range', 30);
        $range = in_array($range, [7, 30], true) ? $range : 30;
        $eventType = strtolower((string) $request->query('event', 'all'));
        $eventType = in_array($eventType, ['all', 'click', 'borrow'], true) ? $eventType : 'all';

        $branches = collect();
        if (Schema::hasTable('branches')) {
            $branches = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get();
        }

        $cacheKey = "nbk:admin:metrics:{$institutionId}:{$branchId}:{$range}:{$eventType}";
        $data = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($institutionId, $branchId, $range, $eventType) {
            $hasEvents = Schema::hasTable('biblio_events');
            $from = now()->subDays($range - 1)->startOfDay();

            $clickSeries = [];
            $borrowSeries = [];
            $topClicked = collect();
            $topBorrowed = collect();
            $recentClicks = collect();
            $recentBorrows = collect();
            $totals = (object) ['clicks' => 0, 'borrows' => 0];

            if ($hasEvents) {
                $eventsBase = DB::table('biblio_events')
                    ->where('institution_id', $institutionId);

                if ($branchId) {
                    $eventsBase->where('branch_id', $branchId);
                }

                $totalsQuery = (clone $eventsBase)
                    ->where('created_at', '>=', $from);
                $totalsRow = $totalsQuery
                    ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks")
                    ->selectRaw("SUM(CASE WHEN event_type = 'borrow' THEN 1 ELSE 0 END) as borrows")
                    ->first();
                $totals = (object) [
                    'clicks' => (int) ($totalsRow->clicks ?? 0),
                    'borrows' => (int) ($totalsRow->borrows ?? 0),
                ];

                $seriesBase = (clone $eventsBase)->where('created_at', '>=', $from);
                if ($eventType !== 'all') {
                    $seriesBase->where('event_type', $eventType);
                }
                $series = $seriesBase
                    ->selectRaw("DATE(created_at) as day, event_type, COUNT(*) as total")
                    ->groupBy('day', 'event_type')
                    ->orderBy('day')
                    ->get();

                for ($i = 0; $i < $range; $i++) {
                    $day = $from->copy()->addDays($i)->format('Y-m-d');
                    $clickSeries[$day] = 0;
                    $borrowSeries[$day] = 0;
                }
                foreach ($series as $row) {
                    if ($row->event_type === 'click') {
                        $clickSeries[$row->day] = (int) $row->total;
                    } elseif ($row->event_type === 'borrow') {
                        $borrowSeries[$row->day] = (int) $row->total;
                    }
                }

                $topClicked = DB::table('biblio_events as be')
                    ->join('biblio as b', 'b.id', '=', 'be.biblio_id')
                    ->where('be.institution_id', $institutionId)
                    ->where('be.event_type', 'click')
                    ->where('be.created_at', '>=', $from)
                    ->when($branchId, fn($q) => $q->where('be.branch_id', $branchId))
                    ->selectRaw('b.id, b.title, b.cover_path, COUNT(*) as click_count')
                    ->groupBy('b.id', 'b.title', 'b.cover_path')
                    ->orderByDesc('click_count')
                    ->limit(8)
                    ->get();

                $topBorrowed = DB::table('biblio_events as be')
                    ->join('biblio as b', 'b.id', '=', 'be.biblio_id')
                    ->where('be.institution_id', $institutionId)
                    ->where('be.event_type', 'borrow')
                    ->where('be.created_at', '>=', $from)
                    ->when($branchId, fn($q) => $q->where('be.branch_id', $branchId))
                    ->selectRaw('b.id, b.title, b.cover_path, COUNT(*) as borrow_count')
                    ->groupBy('b.id', 'b.title', 'b.cover_path')
                    ->orderByDesc('borrow_count')
                    ->limit(8)
                    ->get();

                $recentClicks = DB::table('biblio_events as be')
                    ->join('biblio as b', 'b.id', '=', 'be.biblio_id')
                    ->where('be.institution_id', $institutionId)
                    ->where('be.event_type', 'click')
                    ->when($branchId, fn($q) => $q->where('be.branch_id', $branchId))
                    ->orderByDesc('be.created_at')
                    ->limit(6)
                    ->get([
                        'b.id', 'b.title', 'b.cover_path', 'be.created_at as last_clicked_at'
                    ]);

                $recentBorrows = DB::table('biblio_events as be')
                    ->join('biblio as b', 'b.id', '=', 'be.biblio_id')
                    ->where('be.institution_id', $institutionId)
                    ->where('be.event_type', 'borrow')
                    ->when($branchId, fn($q) => $q->where('be.branch_id', $branchId))
                    ->orderByDesc('be.created_at')
                    ->limit(6)
                    ->get([
                        'b.id', 'b.title', 'b.cover_path', 'be.created_at as last_borrowed_at'
                    ]);
            }

            return [
                'totals' => $totals,
                'topClicked' => $topClicked,
                'topBorrowed' => $topBorrowed,
                'recentClicks' => $recentClicks,
                'recentBorrows' => $recentBorrows,
                'clickSeries' => $clickSeries,
                'borrowSeries' => $borrowSeries,
            ];
        });

        $autoUsage = collect();
        $autoTotal = 0;
        $autoDaily = collect();
        $autoDailyMax = 0;
        $autoPerRecord = null;
        $autoRecordCount = 0;
        $autoFieldCoverage = 0;
        $autoPeakDay = null;
        $autoTopUsers = collect();
        $autoTopPaths = collect();
        $autoTopUserShare = null;
        $autoSuggestions = [];
        if (Schema::hasTable('autocomplete_telemetry')) {
            $fromDay = now()->subDays($range - 1)->toDateString();
            $autoTotal = (int) DB::table('autocomplete_telemetry')
                ->where('institution_id', $institutionId)
                ->where('day', '>=', $fromDay)
                ->sum('count');

            $autoDaily = DB::table('autocomplete_telemetry')
                ->where('institution_id', $institutionId)
                ->where('day', '>=', $fromDay)
                ->selectRaw('day, SUM(count) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->get();
            $dailyMap = $autoDaily->keyBy('day');
            $autoDaily = collect();
            for ($i = 0; $i < $range; $i++) {
                $day = now()->subDays($range - 1 - $i)->toDateString();
                $autoDaily->push((object) [
                    'day' => $day,
                    'total' => (int) ($dailyMap[$day]->total ?? 0),
                ]);
            }
            $autoDailyMax = (int) ($autoDaily->max('total') ?? 0);
            $autoPeakDay = $autoDaily->sortByDesc('total')->first();

            $autoUsage = DB::table('autocomplete_telemetry')
                ->where('institution_id', $institutionId)
                ->where('day', '>=', $fromDay)
                ->selectRaw('field, SUM(count) as total')
                ->groupBy('field')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            $knownFields = ['authors', 'subjects', 'publisher', 'title', 'isbn'];
            $autoFieldDistinct = (int) DB::table('autocomplete_telemetry')
                ->where('institution_id', $institutionId)
                ->where('day', '>=', $fromDay)
                ->distinct('field')
                ->count('field');
            $autoFieldCoverage = count($knownFields) > 0
                ? (int) round(($autoFieldDistinct / count($knownFields)) * 100)
                : 0;

            $autoTopUsers = DB::table('autocomplete_telemetry')
                ->leftJoin('users', 'users.id', '=', 'autocomplete_telemetry.user_id')
                ->where('autocomplete_telemetry.institution_id', $institutionId)
                ->where('autocomplete_telemetry.day', '>=', $fromDay)
                ->selectRaw('autocomplete_telemetry.user_id, COALESCE(users.name, "(Anon)") as name, SUM(autocomplete_telemetry.count) as total')
                ->groupBy('autocomplete_telemetry.user_id', 'users.name')
                ->orderByDesc('total')
                ->limit(4)
                ->get();
            if ($autoTotal > 0 && $autoTopUsers->isNotEmpty()) {
                $autoTopUserShare = (int) round(((int) $autoTopUsers->first()->total / $autoTotal) * 100);
            }

            $autoTopPaths = DB::table('autocomplete_telemetry')
                ->where('institution_id', $institutionId)
                ->where('day', '>=', $fromDay)
                ->whereNotNull('path')
                ->selectRaw('path, SUM(count) as total')
                ->groupBy('path')
                ->orderByDesc('total')
                ->limit(4)
                ->get();

            $autoRecordCount = (int) DB::table('biblio')
                ->where('institution_id', $institutionId)
                ->whereDate('created_at', '>=', $fromDay)
                ->count();
            if ($autoRecordCount > 0) {
                $autoPerRecord = round($autoTotal / $autoRecordCount, 2);
            }

            if ($autoTotal === 0) {
                $autoSuggestions[] = 'Belum ada pemakaian autocomplete tercatat. Dorong staf memakai saran otomatis.';
            } else {
                if ($autoPerRecord !== null && $autoPerRecord < 1) {
                    $autoSuggestions[] = 'Rasio autocomplete per record masih rendah. Tambahkan pelatihan singkat untuk staf katalog.';
                }
                if ($autoDaily->count() > 0) {
                    $lastDay = $autoDaily->last();
                    if ($lastDay && (int) $lastDay->total === 0) {
                        $autoSuggestions[] = 'Tidak ada penggunaan autocomplete hari ini. Cek apakah akses autocomplete berjalan.';
                    }
                }
                $top = $autoUsage->first();
                if ($top && $autoTotal > 0 && ($top->total / $autoTotal) > 0.7) {
                    $autoSuggestions[] = 'Pemakaian sangat terkonsentrasi di satu field. Cek apakah field lain perlu dibantu autocomplete.';
                }
                $publisherRow = $autoUsage->firstWhere('field', 'publisher');
                if (!$publisherRow || (int) $publisherRow->total < max(2, (int) round($autoTotal * 0.1))) {
                    $autoSuggestions[] = 'Autocomplete penerbit rendah; pertimbangkan perbaikan authority publisher.';
                }
                $subjectRow = $autoUsage->firstWhere('field', 'subjects');
                if (!$subjectRow || (int) $subjectRow->total < max(2, (int) round($autoTotal * 0.08))) {
                    $autoSuggestions[] = 'Pemakaian autocomplete subjek rendah; perlu training atau perbaiki data subjek.';
                }
                if ($autoFieldCoverage > 0 && $autoFieldCoverage < 60) {
                    $autoSuggestions[] = 'Cakupan field autocomplete rendah. Aktifkan autocomplete di field lain untuk efisiensi.';
                }
                if ($autoTopUserShare !== null && $autoTopUserShare >= 60) {
                    $autoSuggestions[] = 'Pemakaian autocomplete terkonsentrasi pada satu staf. Lakukan sosialisasi singkat agar tim merata.';
                }
            }
        }

        $interop = InteropMetrics::snapshot();
        $health = (array) data_get($interop, 'health', []);
        $interopP95 = (int) ($health['p95_ms'] ?? 0);
        $interopInvalid = (int) ($health['invalid_token_total'] ?? 0);
        $interopLimited = (int) ($health['rate_limited_total'] ?? 0);
        $interopHealth = (string) ($health['label'] ?? 'Sehat');
        $opac = OpacMetrics::snapshot();
        $opacP95 = (int) data_get($opac, 'latency.p95_ms', 0);
        $opacP50 = (int) data_get($opac, 'latency.p50_ms', 0);
        $opacReq = (int) data_get($opac, 'requests', 0);
        $opacErrRate = (float) data_get($opac, 'error_rate_pct', 0);
        $opacHistory24h = (array) data_get($opac, 'history.last_24h', []);
        $opacP95Series24h = array_values(array_map(fn($r) => (int) ($r['p95_ms'] ?? 0), $opacHistory24h));
        $opacSlo = (array) data_get($opac, 'slo', []);
        $uatSummary = [
            'pass' => 0,
            'fail' => 0,
            'pending' => 0,
        ];
        $uatRecent = collect();
        if (Schema::hasTable('uat_signoffs')) {
            $uatBase = DB::table('uat_signoffs')
                ->where(function ($q) use ($institutionId) {
                    $q->where('institution_id', $institutionId)
                        ->orWhereNull('institution_id');
                });

            $uatSummary['pass'] = (int) (clone $uatBase)->where('status', 'pass')->count();
            $uatSummary['fail'] = (int) (clone $uatBase)->where('status', 'fail')->count();
            $uatSummary['pending'] = (int) (clone $uatBase)->where('status', 'pending')->count();

            $uatRecent = (clone $uatBase)
                ->orderByDesc('check_date')
                ->orderByDesc('signed_at')
                ->limit(12)
                ->get([
                    'check_date',
                    'status',
                    'operator_name',
                    'signed_at',
                    'notes',
                    'checklist_file',
                ]);
        }

        return view('admin.dashboard', [
            'totals' => $data['totals'],
            'topClicked' => $data['topClicked'],
            'topBorrowed' => $data['topBorrowed'],
            'recentClicks' => $data['recentClicks'],
            'recentBorrows' => $data['recentBorrows'],
            'clickSeries' => $data['clickSeries'],
            'borrowSeries' => $data['borrowSeries'],
            'branchId' => $branchId,
            'range' => $range,
            'eventType' => $eventType,
            'branches' => $branches,
            'autoUsage' => $autoUsage,
            'autoTotal' => $autoTotal,
            'autoDaily' => $autoDaily,
            'autoDailyMax' => $autoDailyMax,
            'autoPerRecord' => $autoPerRecord,
            'autoRecordCount' => $autoRecordCount,
            'autoFieldCoverage' => $autoFieldCoverage,
            'autoPeakDay' => $autoPeakDay,
            'autoTopUsers' => $autoTopUsers,
            'autoTopPaths' => $autoTopPaths,
            'autoTopUserShare' => $autoTopUserShare,
            'autoSuggestions' => $autoSuggestions,
            'interopMetrics' => $interop,
            'interopP95' => $interopP95,
            'interopInvalid' => $interopInvalid,
            'interopLimited' => $interopLimited,
            'interopHealth' => $interopHealth,
            'opacMetrics' => $opac,
            'opacP95' => $opacP95,
            'opacP50' => $opacP50,
            'opacRequests' => $opacReq,
            'opacErrorRate' => $opacErrRate,
            'opacP95Series24h' => $opacP95Series24h,
            'opacSlo' => $opacSlo,
            'uatSummary' => $uatSummary,
            'uatRecent' => $uatRecent,
        ]);
    }
}
