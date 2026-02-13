<?php

namespace App\Http\Controllers;

use App\Models\Biblio;
use App\Models\Branch;
use App\Models\SerialIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SerialIssueController extends Controller
{
    private function institutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index(Request $request)
    {
        $institutionId = $this->institutionId();
        $tableReady = Schema::hasTable('serial_issues');
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $branchId = (int) $request->query('branch_id', 0);
        [$from, $to] = $this->resolveDateRange(
            (string) $request->query('from', ''),
            (string) $request->query('to', '')
        );

        $issues = $tableReady
            ? SerialIssue::query()
                ->where('institution_id', $institutionId)
                ->with(['biblio:id,title', 'branch:id,name'])
                ->when($q !== '', function ($builder) use ($q) {
                    $builder->where(function ($w) use ($q) {
                        $w->where('issue_code', 'like', "%{$q}%")
                            ->orWhere('volume', 'like', "%{$q}%")
                            ->orWhere('issue_no', 'like', "%{$q}%")
                            ->orWhereHas('biblio', fn($b) => $b->where('title', 'like', "%{$q}%"));
                    });
                })
                ->when(in_array($status, ['expected', 'received', 'missing', 'claimed'], true), function ($builder) use ($status) {
                    $builder->where('status', $status);
                })
                ->when($branchId > 0, fn($builder) => $builder->where('branch_id', $branchId))
                ->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(expected_on, created_at)'), '>=', $from)
                ->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(expected_on, created_at)'), '<=', $to)
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString()
            : collect();

        $biblios = Biblio::query()
            ->where('institution_id', $institutionId)
            ->where(function ($qBuilder) {
                $qBuilder->where('material_type', 'serial')
                    ->orWhere('material_type', 'like', '%jurnal%')
                    ->orWhere('material_type', 'like', '%majalah%');
            })
            ->orderBy('title')
            ->limit(200)
            ->get(['id', 'title']);

        $branches = Branch::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $summary = $tableReady ? $this->summary($institutionId, $branchId, $from, $to) : [
            'total' => 0,
            'expected' => 0,
            'received' => 0,
            'missing' => 0,
            'claimed' => 0,
            'late_expected' => 0,
        ];

        return view('serial_issues.index', [
            'issues' => $issues,
            'q' => $q,
            'status' => $status,
            'branchId' => $branchId,
            'from' => $from,
            'to' => $to,
            'biblios' => $biblios,
            'branches' => $branches,
            'tableReady' => $tableReady,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('serial_issues')) {
            return redirect()->route('serial_issues.index')
                ->with('error', 'Tabel serial_issues belum tersedia. Jalankan migrasi terlebih dahulu.');
        }

        $institutionId = $this->institutionId();
        $data = $request->validate([
            'biblio_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'issue_code' => ['required', 'string', 'max:80'],
            'volume' => ['nullable', 'string', 'max:60'],
            'issue_no' => ['nullable', 'string', 'max:60'],
            'published_on' => ['nullable', 'date'],
            'expected_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->where('id', (int) $data['biblio_id'])
            ->firstOrFail();

        SerialIssue::query()->create([
            'institution_id' => $institutionId,
            'biblio_id' => (int) $biblio->id,
            'branch_id' => !empty($data['branch_id']) ? (int) $data['branch_id'] : null,
            'issue_code' => trim((string) $data['issue_code']),
            'volume' => trim((string) ($data['volume'] ?? '')) ?: null,
            'issue_no' => trim((string) ($data['issue_no'] ?? '')) ?: null,
            'published_on' => $data['published_on'] ?? null,
            'expected_on' => $data['expected_on'] ?? null,
            'status' => 'expected',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);

        return redirect()->route('serial_issues.index')->with('success', 'Serial issue berhasil dibuat.');
    }

    public function receive(int $id)
    {
        if (!Schema::hasTable('serial_issues')) {
            return redirect()->route('serial_issues.index')
                ->with('error', 'Tabel serial_issues belum tersedia. Jalankan migrasi terlebih dahulu.');
        }

        $institutionId = $this->institutionId();
        $issue = SerialIssue::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        if ((string) $issue->status === 'received') {
            return redirect()->route('serial_issues.index')->with('success', 'Issue sudah berstatus diterima.');
        }

        $update = [
            'status' => 'received',
            'received_at' => now(),
            'received_by' => auth()->id(),
        ];
        if (Schema::hasColumn('serial_issues', 'claimed_at')) {
            $update['claimed_at'] = null;
        }
        if (Schema::hasColumn('serial_issues', 'claimed_by')) {
            $update['claimed_by'] = null;
        }
        if (Schema::hasColumn('serial_issues', 'claim_reference')) {
            $update['claim_reference'] = null;
        }
        if (Schema::hasColumn('serial_issues', 'claim_notes')) {
            $update['claim_notes'] = null;
        }
        $issue->update($update);

        return redirect()->route('serial_issues.index')->with('success', 'Issue ditandai diterima.');
    }

    public function markMissing(int $id)
    {
        if (!Schema::hasTable('serial_issues')) {
            return redirect()->route('serial_issues.index')
                ->with('error', 'Tabel serial_issues belum tersedia. Jalankan migrasi terlebih dahulu.');
        }

        $institutionId = $this->institutionId();
        $issue = SerialIssue::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        if ((string) $issue->status === 'missing') {
            return redirect()->route('serial_issues.index')->with('success', 'Issue sudah berstatus missing.');
        }

        $issue->update([
            'status' => 'missing',
            'received_at' => null,
            'received_by' => null,
        ]);

        return redirect()->route('serial_issues.index')->with('success', 'Issue ditandai missing.');
    }

    public function claim(Request $request, int $id)
    {
        if (!Schema::hasTable('serial_issues')) {
            return redirect()->route('serial_issues.index')
                ->with('error', 'Tabel serial_issues belum tersedia. Jalankan migrasi terlebih dahulu.');
        }

        $institutionId = $this->institutionId();
        $issue = SerialIssue::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        if ((string) $issue->status === 'received') {
            return redirect()->route('serial_issues.index')->with('error', 'Issue yang sudah diterima tidak dapat diklaim.');
        }

        $data = $request->validate([
            'claim_reference' => ['nullable', 'string', 'max:120'],
            'claim_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $update = [
            'status' => 'claimed',
            'received_at' => null,
            'received_by' => null,
        ];

        if (Schema::hasColumn('serial_issues', 'claimed_at')) {
            $update['claimed_at'] = now();
        }
        if (Schema::hasColumn('serial_issues', 'claimed_by')) {
            $update['claimed_by'] = auth()->id();
        }
        if (Schema::hasColumn('serial_issues', 'claim_reference')) {
            $update['claim_reference'] = trim((string) ($data['claim_reference'] ?? '')) ?: null;
        }
        if (Schema::hasColumn('serial_issues', 'claim_notes')) {
            $update['claim_notes'] = trim((string) ($data['claim_notes'] ?? '')) ?: null;
        }

        $issue->update($update);

        return redirect()->route('serial_issues.index')->with('success', 'Issue ditandai claimed ke vendor.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $institutionId = $this->institutionId();
        $branchId = (int) $request->query('branch_id', 0);
        $status = trim((string) $request->query('status', ''));
        [$from, $to] = $this->resolveDateRange(
            (string) $request->query('from', ''),
            (string) $request->query('to', '')
        );

        $filename = "serial-issues-{$from}-{$to}.csv";
        $rows = $this->rowsForExport($institutionId, $branchId, $status, $from, $to, 5000);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Issue', 'Judul', 'Status', 'Expected', 'Received', 'Volume', 'Nomor', 'Cabang', 'Ref Klaim', 'Catatan Klaim']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['issue_code'],
                    $row['title'],
                    strtoupper($row['status']),
                    $row['expected_on'],
                    $row['received_at'],
                    $row['volume'],
                    $row['issue_no'],
                    $row['branch_name'],
                    $row['claim_reference'],
                    $row['claim_notes'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportXlsx(Request $request): StreamedResponse
    {
        $institutionId = $this->institutionId();
        $branchId = (int) $request->query('branch_id', 0);
        $status = trim((string) $request->query('status', ''));
        [$from, $to] = $this->resolveDateRange(
            (string) $request->query('from', ''),
            (string) $request->query('to', '')
        );

        $headers = ['Issue', 'Judul', 'Status', 'Expected', 'Received', 'Volume', 'Nomor', 'Cabang', 'Ref Klaim', 'Catatan Klaim'];
        $rows = array_map(function ($row) {
            return [
                $row['issue_code'],
                $row['title'],
                strtoupper($row['status']),
                $row['expected_on'],
                $row['received_at'],
                $row['volume'],
                $row['issue_no'],
                $row['branch_name'],
                $row['claim_reference'],
                $row['claim_notes'],
            ];
        }, $this->rowsForExport($institutionId, $branchId, $status, $from, $to, 5000));

        $xlsxPath = $this->buildSimpleXlsx('SerialIssues', $headers, $rows);
        $filename = "serial-issues-{$from}-{$to}.xlsx";

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

    private function summary(int $institutionId, int $branchId, string $from, string $to): array
    {
        $base = SerialIssue::query()
            ->where('institution_id', $institutionId)
            ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
            ->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(expected_on, created_at)'), '>=', $from)
            ->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(expected_on, created_at)'), '<=', $to);

        $all = (clone $base)->count();
        $expected = (clone $base)->where('status', 'expected')->count();
        $received = (clone $base)->where('status', 'received')->count();
        $missing = (clone $base)->where('status', 'missing')->count();
        $claimed = (clone $base)->where('status', 'claimed')->count();
        $lateExpected = (clone $base)
            ->where('status', 'expected')
            ->whereNotNull('expected_on')
            ->whereDate('expected_on', '<', now()->toDateString())
            ->count();

        return [
            'total' => (int) $all,
            'expected' => (int) $expected,
            'received' => (int) $received,
            'missing' => (int) $missing,
            'claimed' => (int) $claimed,
            'late_expected' => (int) $lateExpected,
        ];
    }

    private function rowsForExport(int $institutionId, int $branchId, string $status, string $from, string $to, int $limit): array
    {
        return SerialIssue::query()
            ->where('institution_id', $institutionId)
            ->with(['biblio:id,title', 'branch:id,name'])
            ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
            ->when(in_array($status, ['expected', 'received', 'missing', 'claimed'], true), fn($q) => $q->where('status', $status))
            ->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(expected_on, created_at)'), '>=', $from)
            ->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(expected_on, created_at)'), '<=', $to)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (SerialIssue $issue) {
                return [
                    'issue_code' => (string) $issue->issue_code,
                    'title' => (string) ($issue->biblio->title ?? '-'),
                    'status' => (string) $issue->status,
                    'expected_on' => $issue->expected_on?->toDateString() ?? '',
                    'received_at' => $issue->received_at?->toDateString() ?? '',
                    'volume' => (string) ($issue->volume ?? ''),
                    'issue_no' => (string) ($issue->issue_no ?? ''),
                    'branch_name' => (string) ($issue->branch->name ?? '-'),
                    'claim_reference' => (string) ($issue->claim_reference ?? ''),
                    'claim_notes' => (string) ($issue->claim_notes ?? ''),
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
            . '<dc:title>NOTOBUKU Serial Issues</dc:title>'
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
