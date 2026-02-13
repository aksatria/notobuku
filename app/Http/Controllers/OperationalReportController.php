<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalReportController extends Controller
{
    private function institutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index(Request $request)
    {
        $institutionId = $this->institutionId();
        [$from, $to] = $this->resolveDateRange(
            (string) $request->query('from', ''),
            (string) $request->query('to', '')
        );
        $branchId = (int) $request->query('branch_id', 0);

        $filters = [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branchId,
        ];

        $kpi = $this->kpi($institutionId, $filters);
        $topTitles = $this->topTitles($institutionId, $filters);
        $topOverdueMembers = $this->topOverdueMembers($institutionId, $filters);
        $finesRows = $this->finesRows($institutionId, $filters);
        $acquisitionRows = $this->acquisitionRows($institutionId, $filters);
        $memberRows = $this->memberRows($institutionId, $filters);
        $serialRows = $this->serialRows($institutionId, $filters);

        $branches = [];
        if (Schema::hasTable('branches')) {
            $branches = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn($b) => ['id' => (int) $b->id, 'name' => (string) $b->name])
                ->all();
        }

        return view('laporan.index', [
            'filters' => $filters,
            'kpi' => $kpi,
            'topTitles' => $topTitles,
            'topOverdueMembers' => $topOverdueMembers,
            'finesRows' => $finesRows,
            'acquisitionRows' => $acquisitionRows,
            'memberRows' => $memberRows,
            'serialRows' => $serialRows,
            'branches' => $branches,
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $institutionId = $this->institutionId();
        [$from, $to] = $this->resolveDateRange(
            (string) $request->query('from', ''),
            (string) $request->query('to', '')
        );
        $branchId = (int) $request->query('branch_id', 0);
        $filters = ['from' => $from, 'to' => $to, 'branch_id' => $branchId];

        $type = strtolower(trim($type));
        $filename = "laporan-{$type}-{$from}-{$to}.csv";

        return response()->streamDownload(function () use ($type, $institutionId, $filters) {
            $out = fopen('php://output', 'w');
            if ($type === 'sirkulasi') {
                fputcsv($out, ['Judul', 'Jumlah Dipinjam']);
                foreach ($this->topTitles($institutionId, $filters, 500) as $row) {
                    fputcsv($out, [$row['title'], $row['borrowed']]);
                }
            } elseif ($type === 'overdue') {
                fputcsv($out, ['Kode Anggota', 'Nama Anggota', 'Item Overdue']);
                foreach ($this->topOverdueMembers($institutionId, $filters, 500) as $row) {
                    fputcsv($out, [$row['member_code'], $row['full_name'], $row['overdue_items']]);
                }
            } elseif ($type === 'denda') {
                fputcsv($out, ['Kode Anggota', 'Nama Anggota', 'Status', 'Jumlah']);
                foreach ($this->finesRows($institutionId, $filters, 1000) as $row) {
                    fputcsv($out, [$row['member_code'], $row['full_name'], $row['status'], $row['amount']]);
                }
            } elseif ($type === 'anggota') {
                fputcsv($out, ['Kode Anggota', 'Nama', 'Status', 'Pinjaman Aktif', 'Overdue Aktif', 'Denda Belum Lunas']);
                foreach ($this->memberRows($institutionId, $filters, 1000) as $row) {
                    fputcsv($out, [
                        $row['member_code'],
                        $row['full_name'],
                        strtoupper($row['status']),
                        $row['active_loans'],
                        $row['overdue_items'],
                        $row['unpaid_fines'],
                    ]);
                }
            } elseif ($type === 'serial') {
                fputcsv($out, ['Issue', 'Judul', 'Status', 'Expected', 'Received', 'Cabang', 'Ref Klaim']);
                foreach ($this->serialRows($institutionId, $filters, 1000) as $row) {
                    fputcsv($out, [
                        $row['issue_code'],
                        $row['title'],
                        strtoupper($row['status']),
                        $row['expected_on'],
                        $row['received_at'],
                        $row['branch_name'],
                        $row['claim_reference'],
                    ]);
                }
            } else {
                fputcsv($out, ['PO', 'Vendor', 'Status', 'Total']);
                foreach ($this->acquisitionRows($institutionId, $filters, 1000) as $row) {
                    fputcsv($out, [$row['po_number'], $row['vendor_name'], $row['status'], $row['total_amount']]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportXlsx(Request $request, string $type): StreamedResponse
    {
        $institutionId = $this->institutionId();
        [$from, $to] = $this->resolveDateRange(
            (string) $request->query('from', ''),
            (string) $request->query('to', '')
        );
        $branchId = (int) $request->query('branch_id', 0);
        $filters = ['from' => $from, 'to' => $to, 'branch_id' => $branchId];
        $type = strtolower(trim($type));

        $headers = [];
        $rows = [];
        $sheetName = 'Laporan';

        if ($type === 'sirkulasi') {
            $headers = ['Judul', 'Jumlah Dipinjam'];
            $rows = array_map(fn($r) => [$r['title'], $r['borrowed']], $this->topTitles($institutionId, $filters, 2000));
            $sheetName = 'Sirkulasi';
        } elseif ($type === 'overdue') {
            $headers = ['Kode Anggota', 'Nama Anggota', 'Item Overdue'];
            $rows = array_map(fn($r) => [$r['member_code'], $r['full_name'], $r['overdue_items']], $this->topOverdueMembers($institutionId, $filters, 2000));
            $sheetName = 'Overdue';
        } elseif ($type === 'denda') {
            $headers = ['Kode Anggota', 'Nama Anggota', 'Status', 'Jumlah'];
            $rows = array_map(fn($r) => [$r['member_code'], $r['full_name'], strtoupper($r['status']), $r['amount']], $this->finesRows($institutionId, $filters, 4000));
            $sheetName = 'Denda';
        } elseif ($type === 'anggota') {
            $headers = ['Kode Anggota', 'Nama', 'Status', 'Pinjaman Aktif', 'Overdue Aktif', 'Denda Belum Lunas'];
            $rows = array_map(fn($r) => [
                $r['member_code'],
                $r['full_name'],
                strtoupper($r['status']),
                $r['active_loans'],
                $r['overdue_items'],
                $r['unpaid_fines'],
            ], $this->memberRows($institutionId, $filters, 4000));
            $sheetName = 'Anggota';
        } elseif ($type === 'serial') {
            $headers = ['Issue', 'Judul', 'Status', 'Expected', 'Received', 'Cabang', 'Ref Klaim'];
            $rows = array_map(fn($r) => [
                $r['issue_code'],
                $r['title'],
                strtoupper($r['status']),
                $r['expected_on'],
                $r['received_at'],
                $r['branch_name'],
                $r['claim_reference'],
            ], $this->serialRows($institutionId, $filters, 4000));
            $sheetName = 'Serial';
        } else {
            $headers = ['PO', 'Vendor', 'Status', 'Total'];
            $rows = array_map(fn($r) => [$r['po_number'], $r['vendor_name'], strtoupper($r['status']), $r['total_amount']], $this->acquisitionRows($institutionId, $filters, 4000));
            $sheetName = 'Pengadaan';
        }

        $xlsxPath = $this->buildSimpleXlsx($sheetName, $headers, $rows);
        $filename = "laporan-{$type}-{$from}-{$to}.xlsx";

        return response()->streamDownload(function () use ($xlsxPath) {
            $stream = fopen($xlsxPath, 'rb');
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
            @unlink($xlsxPath);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function kpi(int $institutionId, array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $branchId = (int) ($filters['branch_id'] ?? 0);

        $loans = 0;
        $returns = 0;
        $overdue = 0;
        $finesAssessed = 0;
        $finesPaid = 0;
        $purchaseOrders = 0;
        $members = 0;
        $serialOpen = 0;

        if (Schema::hasTable('loans')) {
            $loans = (int) DB::table('loans')
                ->where('institution_id', $institutionId)
                ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
                ->whereDate('loaned_at', '>=', $from)
                ->whereDate('loaned_at', '<=', $to)
                ->count();
        }

        if (Schema::hasTable('loan_items') && Schema::hasTable('loans')) {
            $returns = (int) DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id')
                ->where('l.institution_id', $institutionId)
                ->when($branchId > 0, fn($q) => $q->where('l.branch_id', $branchId))
                ->whereNotNull('li.returned_at')
                ->whereDate('li.returned_at', '>=', $from)
                ->whereDate('li.returned_at', '<=', $to)
                ->count();

            $overdue = (int) DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id')
                ->where('l.institution_id', $institutionId)
                ->when($branchId > 0, fn($q) => $q->where('l.branch_id', $branchId))
                ->where('li.status', 'borrowed')
                ->whereNotNull('li.due_at')
                ->where('li.due_at', '<', now())
                ->count();
        }

        if (Schema::hasTable('fines')) {
            $finesAssessed = (float) DB::table('fines')
                ->where('institution_id', $institutionId)
                ->whereDate('assessed_at', '>=', $from)
                ->whereDate('assessed_at', '<=', $to)
                ->sum('amount');

            $finesPaid = (float) DB::table('fines')
                ->where('institution_id', $institutionId)
                ->where('status', 'paid')
                ->whereDate('paid_at', '>=', $from)
                ->whereDate('paid_at', '<=', $to)
                ->sum(DB::raw('COALESCE(paid_amount, amount)'));
        }

        if (Schema::hasTable('purchase_orders')) {
            $purchaseOrders = (int) DB::table('purchase_orders')
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to)
                ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
                ->count();
        }

        if (Schema::hasTable('members')) {
            $members = (int) DB::table('members')
                ->where('institution_id', $institutionId)
                ->whereDate(DB::raw('COALESCE(joined_at, created_at)'), '>=', $from)
                ->whereDate(DB::raw('COALESCE(joined_at, created_at)'), '<=', $to)
                ->count();
        }

        if (Schema::hasTable('serial_issues')) {
            $serialOpen = (int) DB::table('serial_issues')
                ->where('institution_id', $institutionId)
                ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
                ->whereIn('status', ['expected', 'missing', 'claimed'])
                ->count();
        }

        return [
            'loans' => $loans,
            'returns' => $returns,
            'overdue' => $overdue,
            'fines_assessed' => $finesAssessed,
            'fines_paid' => $finesPaid,
            'purchase_orders' => $purchaseOrders,
            'new_members' => $members,
            'serial_open' => $serialOpen,
        ];
    }

    private function topTitles(int $institutionId, array $filters, int $limit = 20): array
    {
        if (!Schema::hasTable('loan_items') || !Schema::hasTable('loans') || !Schema::hasTable('items') || !Schema::hasTable('biblio')) {
            return [];
        }

        $from = $filters['from'];
        $to = $filters['to'];
        $branchId = (int) ($filters['branch_id'] ?? 0);

        return DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            ->join('biblio as b', 'b.id', '=', 'i.biblio_id')
            ->where('l.institution_id', $institutionId)
            ->when($branchId > 0, fn($q) => $q->where('l.branch_id', $branchId))
            ->whereDate('li.borrowed_at', '>=', $from)
            ->whereDate('li.borrowed_at', '<=', $to)
            ->groupBy('b.id', 'b.title')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->get([
                'b.title',
                DB::raw('COUNT(*) as borrowed'),
            ])
            ->map(fn($r) => ['title' => (string) $r->title, 'borrowed' => (int) $r->borrowed])
            ->all();
    }

    private function topOverdueMembers(int $institutionId, array $filters, int $limit = 20): array
    {
        if (!Schema::hasTable('loan_items') || !Schema::hasTable('loans') || !Schema::hasTable('members')) {
            return [];
        }

        $branchId = (int) ($filters['branch_id'] ?? 0);

        return DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->join('members as m', 'm.id', '=', 'l.member_id')
            ->where('l.institution_id', $institutionId)
            ->when($branchId > 0, fn($q) => $q->where('l.branch_id', $branchId))
            ->where('li.status', 'borrowed')
            ->whereNotNull('li.due_at')
            ->where('li.due_at', '<', now())
            ->groupBy('m.id', 'm.member_code', 'm.full_name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->get([
                'm.member_code',
                'm.full_name',
                DB::raw('COUNT(*) as overdue_items'),
            ])
            ->map(function ($r) {
                return [
                    'member_code' => (string) $r->member_code,
                    'full_name' => (string) $r->full_name,
                    'overdue_items' => (int) $r->overdue_items,
                ];
            })->all();
    }

    private function finesRows(int $institutionId, array $filters, int $limit = 20): array
    {
        if (!Schema::hasTable('fines') || !Schema::hasTable('members')) {
            return [];
        }

        $from = $filters['from'];
        $to = $filters['to'];

        return DB::table('fines as f')
            ->join('members as m', 'm.id', '=', 'f.member_id')
            ->where('f.institution_id', $institutionId)
            ->whereDate('f.assessed_at', '>=', $from)
            ->whereDate('f.assessed_at', '<=', $to)
            ->orderByDesc('f.assessed_at')
            ->limit($limit)
            ->get([
                'm.member_code',
                'm.full_name',
                'f.status',
                'f.amount',
            ])
            ->map(function ($r) {
                return [
                    'member_code' => (string) $r->member_code,
                    'full_name' => (string) $r->full_name,
                    'status' => (string) $r->status,
                    'amount' => (float) $r->amount,
                ];
            })->all();
    }

    private function acquisitionRows(int $institutionId, array $filters, int $limit = 20): array
    {
        if (!Schema::hasTable('purchase_orders') || !Schema::hasTable('vendors')) {
            return [];
        }

        $from = $filters['from'];
        $to = $filters['to'];
        $branchId = (int) ($filters['branch_id'] ?? 0);

        return DB::table('purchase_orders as po')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->whereDate('po.created_at', '>=', $from)
            ->whereDate('po.created_at', '<=', $to)
            ->when($branchId > 0, fn($q) => $q->where('po.branch_id', $branchId))
            ->orderByDesc('po.created_at')
            ->limit($limit)
            ->get([
                'po.po_number',
                'po.status',
                'po.total_amount',
                DB::raw('COALESCE(v.name, \'-\') as vendor_name'),
            ])
            ->map(function ($r) {
                return [
                    'po_number' => (string) $r->po_number,
                    'status' => (string) $r->status,
                    'vendor_name' => (string) $r->vendor_name,
                    'total_amount' => (float) $r->total_amount,
                ];
            })->all();
    }

    private function memberRows(int $institutionId, array $filters, int $limit = 20): array
    {
        if (!Schema::hasTable('members')) {
            return [];
        }

        $from = $filters['from'];
        $to = $filters['to'];

        $baseRows = DB::table('members as m')
            ->where('m.institution_id', $institutionId)
            ->whereDate(DB::raw('COALESCE(m.joined_at, m.created_at)'), '>=', $from)
            ->whereDate(DB::raw('COALESCE(m.joined_at, m.created_at)'), '<=', $to)
            ->orderByDesc(DB::raw('COALESCE(m.joined_at, m.created_at)'))
            ->limit($limit)
            ->get([
                'm.id',
                'm.member_code',
                'm.full_name',
                'm.status',
            ]);

        if ($baseRows->isEmpty()) {
            return [];
        }

        $memberIds = $baseRows->pluck('id')->map(fn($id) => (int) $id)->all();

        $activeMap = [];
        $overdueMap = [];
        $unpaidMap = [];

        if (Schema::hasTable('loan_items') && Schema::hasTable('loans')) {
            $activeMap = DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id')
                ->where('l.institution_id', $institutionId)
                ->whereIn('l.member_id', $memberIds)
                ->where('li.status', 'borrowed')
                ->selectRaw('l.member_id as member_id, COUNT(*) as cnt')
                ->groupBy('l.member_id')
                ->pluck('cnt', 'member_id')
                ->map(fn($v) => (int) $v)
                ->all();

            $overdueMap = DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id')
                ->where('l.institution_id', $institutionId)
                ->whereIn('l.member_id', $memberIds)
                ->where('li.status', 'borrowed')
                ->whereNotNull('li.due_at')
                ->where('li.due_at', '<', now())
                ->selectRaw('l.member_id as member_id, COUNT(*) as cnt')
                ->groupBy('l.member_id')
                ->pluck('cnt', 'member_id')
                ->map(fn($v) => (int) $v)
                ->all();
        }

        if (Schema::hasTable('fines')) {
            $unpaidMap = DB::table('fines')
                ->where('institution_id', $institutionId)
                ->whereIn('member_id', $memberIds)
                ->where('status', 'unpaid')
                ->selectRaw('member_id, SUM(amount) as total')
                ->groupBy('member_id')
                ->pluck('total', 'member_id')
                ->map(fn($v) => (float) $v)
                ->all();
        }

        return $baseRows->map(function ($row) use ($activeMap, $overdueMap, $unpaidMap) {
            $id = (int) $row->id;
            return [
                'member_code' => (string) $row->member_code,
                'full_name' => (string) $row->full_name,
                'status' => (string) $row->status,
                'active_loans' => (int) ($activeMap[$id] ?? 0),
                'overdue_items' => (int) ($overdueMap[$id] ?? 0),
                'unpaid_fines' => (float) ($unpaidMap[$id] ?? 0),
            ];
        })->all();
    }

    private function serialRows(int $institutionId, array $filters, int $limit = 20): array
    {
        if (!Schema::hasTable('serial_issues') || !Schema::hasTable('biblio')) {
            return [];
        }

        $from = $filters['from'];
        $to = $filters['to'];
        $branchId = (int) ($filters['branch_id'] ?? 0);

        return DB::table('serial_issues as si')
            ->join('biblio as b', 'b.id', '=', 'si.biblio_id')
            ->leftJoin('branches as br', 'br.id', '=', 'si.branch_id')
            ->where('si.institution_id', $institutionId)
            ->when($branchId > 0, fn($q) => $q->where('si.branch_id', $branchId))
            ->where(function ($q) use ($from, $to) {
                $q->whereDate(DB::raw('COALESCE(si.expected_on, si.created_at)'), '>=', $from)
                    ->whereDate(DB::raw('COALESCE(si.expected_on, si.created_at)'), '<=', $to);
            })
            ->orderByDesc('si.created_at')
            ->limit($limit)
            ->get([
                'si.issue_code',
                'si.status',
                'si.expected_on',
                'si.received_at',
                'si.claim_reference',
                DB::raw('COALESCE(br.name, \'-\') as branch_name'),
                'b.title',
            ])
            ->map(function ($r) {
                return [
                    'issue_code' => (string) $r->issue_code,
                    'title' => (string) $r->title,
                    'status' => (string) $r->status,
                    'expected_on' => $r->expected_on ? (string) \Illuminate\Support\Carbon::parse($r->expected_on)->toDateString() : '',
                    'received_at' => $r->received_at ? (string) \Illuminate\Support\Carbon::parse($r->received_at)->toDateString() : '',
                    'branch_name' => (string) $r->branch_name,
                    'claim_reference' => (string) ($r->claim_reference ?? ''),
                ];
            })
            ->all();
    }

    private function resolveDateRange(string $fromInput, string $toInput): array
    {
        $fallbackFrom = now()->subDays(30)->toDateString();
        $fallbackTo = now()->toDateString();

        $from = $this->normalizeDate($fromInput, $fallbackFrom);
        $to = $this->normalizeDate($toInput, $fallbackTo);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function normalizeDate(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    private function buildSimpleXlsx(string $sheetName, array $headers, array $rows): string
    {
        if (!class_exists(\ZipArchive::class)) {
            abort(500, 'ZipArchive extension tidak tersedia untuk export XLSX.');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'nbk_xlsx_');
        if ($tmpPath === false) {
            abort(500, 'Tidak bisa membuat file sementara XLSX.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpPath, \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Gagal membuat arsip XLSX.');
        }

        $safeSheetName = preg_replace('/[^A-Za-z0-9 _-]/', '', $sheetName) ?: 'Sheet1';
        $sheetXml = $this->buildSheetXml($headers, $rows);
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($safeSheetName, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>NOTOBUKU Report</dc:title>'
            . '<dc:creator>NOTOBUKU</dc:creator>'
            . '<cp:lastModifiedBy>NOTOBUKU</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . now()->toIso8601String() . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . now()->toIso8601String() . '</dcterms:modified>'
            . '</cp:coreProperties>';
        $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>NOTOBUKU</Application>'
            . '</Properties>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('docProps/core.xml', $core);
        $zip->addFromString('docProps/app.xml', $app);
        $zip->close();

        return $tmpPath;
    }

    private function buildSheetXml(array $headers, array $rows): string
    {
        $allRows = [];
        $allRows[] = $headers;
        foreach ($rows as $row) {
            $allRows[] = is_array($row) ? array_values($row) : [(string) $row];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        foreach ($allRows as $rIndex => $rowValues) {
            $rowNumber = $rIndex + 1;
            $xml .= '<row r="' . $rowNumber . '">';
            foreach ($rowValues as $cIndex => $value) {
                $cellRef = $this->xlsxColName($cIndex + 1) . $rowNumber;
                if (is_numeric($value) && !preg_match('/^0\d+/', (string) $value)) {
                    $xml .= '<c r="' . $cellRef . '" t="n"><v>' . (0 + $value) . '</v></c>';
                } else {
                    $escaped = htmlspecialchars((string) $value, ENT_XML1);
                    $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function xlsxColName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = intdiv($index - 1, 26);
        }
        return $name;
    }
}
