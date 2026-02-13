<?php

namespace App\Services;

use App\Jobs\ImportKatalogJob;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Biblio;
use App\Models\Subject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportService
{
    private const QUEUE_THRESHOLD = 2000;
    private const AI_QUEUE_THRESHOLD = 2000;

    public function __construct(private MetadataMappingService $mappingService)
    {
    }

    public function shouldQueue(UploadedFile $file, string $format): bool
    {
        $format = strtolower(trim($format));

        if ($format === 'csv') {
            return $this->countCsvRows($file->getRealPath()) > self::QUEUE_THRESHOLD;
        }

        if (in_array($format, ['dcxml', 'marcxml'], true)) {
            return $this->countXmlRecords($file->getRealPath()) > self::QUEUE_THRESHOLD;
        }

        return false;
    }

    public function shouldQueueAi(int $totalImported): bool
    {
        return $totalImported >= self::AI_QUEUE_THRESHOLD;
    }

    public function queueImport(UploadedFile $file, string $format, int $institutionId, ?int $userId): ?string
    {
        $path = $file->store('imports', 'local');
        ImportKatalogJob::dispatch($path, $format, $institutionId, $userId);

        $this->logImport($format, [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'status' => 'queued',
        ], $userId);

        return $path;
    }

    public function importByFormat(string $format, UploadedFile $file, int $institutionId, ?int $userId): array
    {
        return $this->importByFormatFromPath($format, $file->getRealPath(), $institutionId, $userId);
    }

    public function importByFormatFromPath(string $format, string $path, int $institutionId, ?int $userId): array
    {
        $format = strtolower(trim($format));

        $report = match ($format) {
            'csv' => $this->importCsvFromPath($path, $institutionId, $userId),
            'dcxml' => $this->importDublinCoreXmlFromPath($path, $institutionId, $userId),
            'marcxml' => $this->importMarcXmlCoreFromPath($path, $institutionId, $userId),
            default => [
                'status' => 'invalid_format',
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['Format tidak dikenali.'],
            ],
        };

        $this->logImport($format, $report, $userId);

        return $report;
    }

    public function importCsv(UploadedFile $file, int $institutionId, ?int $userId): array
    {
        return $this->importCsvFromPath($file->getRealPath(), $institutionId, $userId);
    }

    public function importDublinCoreXml(UploadedFile $file, int $institutionId, ?int $userId): array
    {
        return $this->importDublinCoreXmlFromPath($file->getRealPath(), $institutionId, $userId);
    }

    public function importMarcXmlCore(UploadedFile $file, int $institutionId, ?int $userId): array
    {
        return $this->importMarcXmlCoreFromPath($file->getRealPath(), $institutionId, $userId);
    }

    private function importCsvFromPath(string $path, int $institutionId, ?int $userId): array
    {
        $report = $this->baseReport();

        if (!file_exists($path)) {
            $report['errors'][] = 'File CSV tidak ditemukan.';
            return $report;
        }

        DB::beginTransaction();
        try {
            $handle = fopen($path, 'r');
            if ($handle === false) {
                $report['errors'][] = 'Gagal membuka file CSV.';
                DB::rollBack();
                return $report;
            }

            $header = fgetcsv($handle);
            if (!$header) {
                $report['errors'][] = 'Header CSV kosong.';
                fclose($handle);
                DB::rollBack();
                return $report;
            }

            $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if (count($row) === 1 && trim((string) $row[0]) === '') {
                    continue;
                }

                $rowAssoc = [];
                foreach ($header as $i => $key) {
                    $rowAssoc[$key] = $row[$i] ?? null;
                }

                $result = $this->upsertBiblioFromRow($rowAssoc, $institutionId);
                if ($result['status'] === 'error') {
                    $report['errors'][] = "Baris {$rowNumber}: " . $result['message'];
                    $report['skipped']++;
                    continue;
                }

                $report[$result['status']]++;
                if (!empty($result['id'])) {
                    $report['biblio_ids'][] = (int) $result['id'];
                }
            }

            fclose($handle);

            if (!empty($report['errors'])) {
                $report['rolled_back'] = true;
                DB::rollBack();
                return $report;
            }

            DB::commit();
            return $report;
        } catch (\Throwable $e) {
            DB::rollBack();
            $report['errors'][] = $e->getMessage();
            $report['rolled_back'] = true;
            return $report;
        }
    }

    private function importDublinCoreXmlFromPath(string $path, int $institutionId, ?int $userId): array
    {
        $report = $this->baseReport();

        if (!file_exists($path)) {
            $report['errors'][] = 'File Dublin Core XML tidak ditemukan.';
            return $report;
        }

        DB::beginTransaction();
        try {
            $doc = new \DOMDocument();
            $loaded = $doc->load($path);
            if (!$loaded) {
                $report['errors'][] = 'Gagal membaca Dublin Core XML.';
                DB::rollBack();
                return $report;
            }

            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

            $recordNodes = [];
            $records = $doc->getElementsByTagName('record');
            if ($records->length > 0) {
                foreach ($records as $record) {
                    $recordNodes[] = $record;
                }
            } elseif ($doc->documentElement) {
                $recordNodes[] = $doc->documentElement;
            }

            foreach ($recordNodes as $record) {
                $data = [
                    'title' => $this->firstText($xpath, './/dc:title', $record),
                    'creator' => $this->allText($xpath, './/dc:creator', $record),
                    'subject' => $this->allText($xpath, './/dc:subject', $record),
                    'description' => $this->firstText($xpath, './/dc:description', $record),
                    'publisher' => $this->firstText($xpath, './/dc:publisher', $record),
                    'date' => $this->firstText($xpath, './/dc:date', $record),
                    'language' => $this->firstText($xpath, './/dc:language', $record),
                    'identifier' => $this->firstText($xpath, './/dc:identifier', $record),
                    'type' => $this->firstText($xpath, './/dc:type', $record),
                    'format' => $this->firstText($xpath, './/dc:format', $record),
                ];

                $row = [
                    'title' => $data['title'],
                    'authors' => implode(', ', $data['creator']),
                    'subjects' => implode('; ', $data['subject']),
                    'description' => $data['description'],
                    'publisher' => $data['publisher'],
                    'publish_year' => $this->extractYear($data['date']),
                    'language' => $data['language'],
                    'isbn' => $data['identifier'],
                    'material_type' => $data['type'],
                    'media_type' => $data['format'],
                ];

                $result = $this->upsertBiblioFromRow($row, $institutionId);
                if ($result['status'] === 'error') {
                    $report['errors'][] = $result['message'];
                    $report['skipped']++;
                    continue;
                }
                $report[$result['status']]++;
                if (!empty($result['id'])) {
                    $report['biblio_ids'][] = (int) $result['id'];
                }
            }

            if (!empty($report['errors'])) {
                $report['rolled_back'] = true;
                DB::rollBack();
                return $report;
            }

            DB::commit();
            return $report;
        } catch (\Throwable $e) {
            DB::rollBack();
            $report['errors'][] = $e->getMessage();
            $report['rolled_back'] = true;
            return $report;
        }
    }

    private function importMarcXmlCoreFromPath(string $path, int $institutionId, ?int $userId): array
    {
        $report = $this->baseReport();

        if (!file_exists($path)) {
            $report['errors'][] = 'File MARCXML tidak ditemukan.';
            return $report;
        }

        DB::beginTransaction();
        try {
            $doc = new \DOMDocument();
            $loaded = $doc->load($path);
            if (!$loaded) {
                $report['errors'][] = 'Gagal membaca MARCXML.';
                DB::rollBack();
                return $report;
            }

            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

            $records = $xpath->query('//marc:record');
            foreach ($records as $record) {
                $control008 = $this->controlField($xpath, $record, '008');
                $langFrom008 = $this->parseLangFrom008($control008);

                $authorEntries = $this->parseAuthorsFromMarc($xpath, $record);

                $notes500 = $this->allSubfield($xpath, $record, '500', 'a');
                $notes520 = $this->allSubfield($xpath, $record, '520', 'a');

                $seriesTitle = $this->firstSubfield($xpath, $record, '490', 'a')
                    ?? $this->firstSubfield($xpath, $record, '830', 'a');

                $pubPlace = $this->firstSubfield($xpath, $record, '264', 'a')
                    ?? $this->firstSubfield($xpath, $record, '260', 'a');
                $publisher = $this->firstSubfield($xpath, $record, '264', 'b')
                    ?? $this->firstSubfield($xpath, $record, '260', 'b');
                $pubYear = $this->firstSubfield($xpath, $record, '264', 'c')
                    ?? $this->firstSubfield($xpath, $record, '260', 'c');

                $physicalA = $this->firstSubfield($xpath, $record, '300', 'a');
                $physicalB = $this->firstSubfield($xpath, $record, '300', 'b');
                $physicalC = $this->firstSubfield($xpath, $record, '300', 'c');

                $identifiers = $this->parseIdentifiersFromMarc($xpath, $record);

                $row = [
                    'title' => $this->firstSubfield($xpath, $record, '245', 'a'),
                    'subtitle' => $this->firstSubfield($xpath, $record, '245', 'b'),
                    'responsibility_statement' => $this->firstSubfield($xpath, $record, '245', 'c'),
                    'authors' => implode(', ', array_map(fn($a) => $a['name'], $authorEntries)),
                    'authors_with_roles' => $authorEntries,
                    'subjects' => $this->allSubfield($xpath, $record, '650', 'a'),
                    'publisher' => $publisher,
                    'place_of_publication' => $pubPlace,
                    'publish_year' => $pubYear,
                    'isbn' => $this->firstSubfield($xpath, $record, '020', 'a'),
                    'issn' => $this->firstSubfield($xpath, $record, '022', 'a'),
                    'language' => $this->firstSubfield($xpath, $record, '041', 'a') ?? $langFrom008,
                    'edition' => $this->firstSubfield($xpath, $record, '250', 'a'),
                    'series_title' => $seriesTitle,
                    'physical_desc' => $physicalA,
                    'illustrations' => $physicalB,
                    'dimensions' => $physicalC,
                    'ddc' => $this->firstSubfield($xpath, $record, '082', 'a'),
                    'call_number' => $this->firstSubfield($xpath, $record, '090', 'a'),
                    'general_note' => !empty($notes500) ? implode(' ; ', $notes500) : null,
                    'notes' => !empty($notes520) ? implode(' ; ', $notes520) : null,
                    'bibliography_note' => $this->firstSubfield($xpath, $record, '504', 'a'),
                ];

                if (is_array($row['subjects'])) {
                    $row['subjects'] = implode('; ', $row['subjects']);
                }

                if (!empty($identifiers)) {
                    foreach ($identifiers as $key => $value) {
                        $row[$key] = $value;
                    }
                }

                $result = $this->upsertBiblioFromRow($row, $institutionId);
                if ($result['status'] === 'error') {
                    $report['errors'][] = $result['message'];
                    $report['skipped']++;
                    continue;
                }
                $report[$result['status']]++;
                if (!empty($result['id'])) {
                    $report['biblio_ids'][] = (int) $result['id'];
                }
            }

            if (!empty($report['errors'])) {
                $report['rolled_back'] = true;
                DB::rollBack();
                return $report;
            }

            DB::commit();
            return $report;
        } catch (\Throwable $e) {
            DB::rollBack();
            $report['errors'][] = $e->getMessage();
            $report['rolled_back'] = true;
            return $report;
        }
    }

    private function upsertBiblioFromRow(array $row, int $institutionId): array
    {
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            return ['status' => 'error', 'message' => 'Judul wajib diisi.'];
        }

        $subtitle = trim((string) ($row['subtitle'] ?? ''));
        $subtitle = $subtitle !== '' ? $subtitle : null;

        $isbn = trim((string) ($row['isbn'] ?? $row['identifier'] ?? ''));
        $isbn = $isbn !== '' ? $isbn : null;

        $publishYear = $this->extractYear($row['publish_year'] ?? $row['date'] ?? null);

        $normalizedTitle = $this->normalizeTitle($title, $subtitle);

        $query = Biblio::query()->where('institution_id', $institutionId);
        if ($isbn) {
            $query->where('isbn', $isbn);
        } else {
            $query->where('normalized_title', $normalizedTitle);
            if ($publishYear) {
                $query->where('publish_year', $publishYear);
            }
        }

        $biblio = $query->first();
        $statusKey = $biblio ? 'updated' : 'created';

        $payload = [
            'institution_id' => $institutionId,
            'title' => $title,
            'subtitle' => $subtitle,
            'normalized_title' => $normalizedTitle,
            'responsibility_statement' => $this->nullableString($row['responsibility_statement'] ?? null),
            'publisher' => $this->nullableString($row['publisher'] ?? null),
            'place_of_publication' => $this->nullableString($row['place_of_publication'] ?? null),
            'publish_year' => $publishYear,
            'isbn' => $isbn,
            'issn' => $this->nullableString($row['issn'] ?? null),
            'language' => $this->nullableString($row['language'] ?? null) ?: 'id',
            'edition' => $this->nullableString($row['edition'] ?? null),
            'series_title' => $this->nullableString($row['series_title'] ?? null),
            'physical_desc' => $this->nullableString($row['physical_desc'] ?? null),
            'extent' => $this->nullableString($row['extent'] ?? null),
            'dimensions' => $this->nullableString($row['dimensions'] ?? null),
            'illustrations' => $this->nullableString($row['illustrations'] ?? null),
            'material_type' => $this->nullableString($row['material_type'] ?? null) ?: 'buku',
            'media_type' => $this->nullableString($row['media_type'] ?? null) ?: 'teks',
            'ddc' => $this->nullableString($row['ddc'] ?? null),
            'call_number' => $this->nullableString($row['call_number'] ?? null),
            'notes' => $this->nullableString($row['description'] ?? $row['notes'] ?? null),
            'bibliography_note' => $this->nullableString($row['bibliography_note'] ?? null),
            'general_note' => $this->nullableString($row['general_note'] ?? null),
            'ai_status' => 'draft',
        ];

        if ($biblio) {
            $biblio->fill($payload);
            $biblio->save();
        } else {
            $biblio = Biblio::create($payload);
        }

        $authorsText = trim((string) ($row['authors'] ?? $row['author'] ?? $row['creator'] ?? ''));
        $subjectsText = trim((string) ($row['subjects'] ?? $row['subject'] ?? ''));

        if (!empty($row['authors_with_roles']) && is_array($row['authors_with_roles'])) {
            $this->syncAuthorsWithRoles($biblio, $row['authors_with_roles']);
        } else {
            $this->syncAuthors($biblio, $authorsText);
        }
        $this->syncSubjects($biblio, $subjectsText);

        $identifiers = $this->parseIdentifiersFromRow($row);
        $this->mappingService->syncMetadataForBiblio($biblio, null, $identifiers);

        return ['status' => $statusKey, 'id' => $biblio->id];
    }

    private function parseIdentifiersFromRow(array $row): array
    {
        $identifiers = [];

        $map = [
            'doi' => 'doi',
            'uri' => 'uri',
            'url' => 'uri',
            'handle' => 'handle',
            'isni' => 'isni',
            'orcid' => 'orcid',
            'oclc' => 'oclc',
            'lccn' => 'lccn',
            'issn' => 'issn',
        ];

        foreach ($map as $key => $scheme) {
            if (!empty($row[$key])) {
                $identifiers[] = [
                    'scheme' => $scheme,
                    'value' => (string) $row[$key],
                ];
            }
        }

        if (!empty($row['identifier'])) {
            $identifiers[] = [
                'scheme' => 'identifier',
                'value' => (string) $row['identifier'],
            ];
        }

        if (!empty($row['identifiers'])) {
            $parts = preg_split('/[;,]/', (string) $row['identifiers']);
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part === '') continue;
                if (str_contains($part, ':')) {
                    [$scheme, $value] = array_map('trim', explode(':', $part, 2));
                    if ($scheme !== '' && $value !== '') {
                        $identifiers[] = [
                            'scheme' => $scheme,
                            'value' => $value,
                        ];
                    }
                } else {
                    $identifiers[] = [
                        'scheme' => 'identifier',
                        'value' => $part,
                    ];
                }
            }
        }

        return $identifiers;
    }

    private function syncAuthors(Biblio $biblio, string $authorsText): void
    {
        $authors = collect(explode(',', $authorsText))
            ->map(fn($x) => trim((string) $x))
            ->filter()
            ->values();

        $sync = [];
        foreach ($authors as $i => $name) {
            $normalized = $this->normalize($name);
            $author = Author::query()->firstOrCreate(
                ['normalized_name' => $normalized],
                ['name' => $name, 'normalized_name' => $normalized]
            );
            $sync[$author->id] = ['role' => 'pengarang', 'sort_order' => $i + 1];
        }

        if (!empty($sync)) {
            $biblio->authors()->sync($sync);
        }
    }

    private function syncAuthorsWithRoles(Biblio $biblio, array $authors): void
    {
        $sync = [];
        $i = 0;
        foreach ($authors as $row) {
            if (!is_array($row)) continue;
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') continue;
            $role = $this->normalizeAuthorRole($row['role'] ?? null);
            $normalized = $this->normalize($name);
            $author = Author::query()->firstOrCreate(
                ['normalized_name' => $normalized],
                ['name' => $name, 'normalized_name' => $normalized]
            );
            $i++;
            $sync[$author->id] = ['role' => $role, 'sort_order' => $i];
        }

        if (!empty($sync)) {
            $biblio->authors()->sync($sync);
        }
    }

    private function syncSubjects(Biblio $biblio, string $subjectsText): void
    {
        $subjects = collect(preg_split('/[,;\n]/', $subjectsText))
            ->map(fn($x) => trim((string) $x))
            ->filter()
            ->values();

        $sync = [];
        foreach ($subjects as $i => $term) {
            $normalized = $this->normalize($term);
            $subject = Subject::query()->firstOrCreate(
                ['normalized_term' => $normalized],
                ['name' => $term, 'term' => $term, 'normalized_term' => $normalized, 'scheme' => 'local']
            );
            $sync[$subject->id] = ['type' => 'topic', 'sort_order' => $i + 1];
        }

        if (!empty($sync)) {
            $biblio->subjects()->sync($sync);
        }
    }

    private function normalizeTitle(string $title, ?string $subtitle = null): string
    {
        $base = trim($title);
        $subtitle = trim((string) $subtitle);
        if ($subtitle !== '') {
            $base .= ' ' . $subtitle;
        }

        return $this->normalize($base);
    }

    private function normalize(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }

    private function extractYear($value): ?int
    {
        if ($value === null) return null;

        $value = trim((string) $value);
        if ($value === '') return null;

        if (preg_match('/(19|20)\d{2}/', $value, $m)) {
            return (int) $m[0];
        }

        if (is_numeric($value)) {
            $intVal = (int) $value;
            return $intVal > 0 ? $intVal : null;
        }

        return null;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function baseReport(): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'rolled_back' => false,
            'biblio_ids' => [],
        ];
    }

    private function logImport(string $format, array $report, ?int $userId): void
    {
        try {
            $status = $report['status'] ?? null;
            if (!$status) {
                $status = !empty($report['errors'] ?? []) ? 'failed' : 'success';
            }

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'import',
                'format' => $format,
                'status' => $status,
                'meta' => [
                    'created' => (int) ($report['created'] ?? 0),
                    'updated' => (int) ($report['updated'] ?? 0),
                    'skipped' => (int) ($report['skipped'] ?? 0),
                    'errors' => $report['errors'] ?? [],
                    'rolled_back' => (bool) ($report['rolled_back'] ?? false),
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }
    }

    private function firstText(\DOMXPath $xpath, string $query, \DOMNode $context): ?string
    {
        $node = $xpath->query($query, $context)->item(0);
        return $node ? trim((string) $node->textContent) : null;
    }

    private function allText(\DOMXPath $xpath, string $query, \DOMNode $context): array
    {
        $nodes = $xpath->query($query, $context);
        $values = [];
        foreach ($nodes as $node) {
            $text = trim((string) $node->textContent);
            if ($text !== '') {
                $values[] = $text;
            }
        }
        return $values;
    }

    private function firstSubfield(\DOMXPath $xpath, \DOMNode $record, string $tag, string $code): ?string
    {
        $query = ".//marc:datafield[@tag='{$tag}']/marc:subfield[@code='{$code}']";
        $node = $xpath->query($query, $record)->item(0);
        return $node ? trim((string) $node->textContent) : null;
    }

    private function allSubfield(\DOMXPath $xpath, \DOMNode $record, string $tag, string $code): array
    {
        $query = ".//marc:datafield[@tag='{$tag}']/marc:subfield[@code='{$code}']";
        $nodes = $xpath->query($query, $record);
        $values = [];
        foreach ($nodes as $node) {
            $text = trim((string) $node->textContent);
            if ($text !== '') {
                $values[] = $text;
            }
        }
        return $values;
    }

    private function datafields(\DOMXPath $xpath, \DOMNode $record, string $tag): array
    {
        $query = ".//marc:datafield[@tag='{$tag}']";
        $nodes = $xpath->query($query, $record);
        $fields = [];

        foreach ($nodes as $node) {
            $row = [];
            foreach ($xpath->query('.//marc:subfield', $node) as $sub) {
                $code = $sub->attributes?->getNamedItem('code')?->nodeValue ?? null;
                $value = trim((string) $sub->textContent);
                if ($code === null || $value === '') continue;
                if (!isset($row[$code])) {
                    $row[$code] = [];
                }
                $row[$code][] = $value;
            }
            if (!empty($row)) {
                $fields[] = $row;
            }
        }

        return $fields;
    }

    private function parseAuthorsFromMarc(\DOMXPath $xpath, \DOMNode $record): array
    {
        $entries = [];

        $mainTags = ['100', '110', '111'];
        foreach ($mainTags as $tag) {
            foreach ($this->datafields($xpath, $record, $tag) as $field) {
                $name = $field['a'][0] ?? null;
                if (!$name) continue;
                $role = $field['4'][0] ?? $field['e'][0] ?? null;
                $entries[] = [
                    'name' => trim((string) $name),
                    'role' => $this->normalizeAuthorRole($role ?: 'pengarang'),
                ];
            }
        }

        $extraTags = ['700', '710', '711'];
        foreach ($extraTags as $tag) {
            foreach ($this->datafields($xpath, $record, $tag) as $field) {
                $name = $field['a'][0] ?? null;
                if (!$name) continue;
                $role = $field['4'][0] ?? $field['e'][0] ?? null;
                $entries[] = [
                    'name' => trim((string) $name),
                    'role' => $this->normalizeAuthorRole($role ?: 'pengarang'),
                ];
            }
        }

        $unique = [];
        foreach ($entries as $row) {
            $key = strtolower($row['name']) . '::' . strtolower($row['role'] ?? '');
            if (!isset($unique[$key])) {
                $unique[$key] = $row;
            }
        }

        return array_values($unique);
    }

    private function normalizeAuthorRole($value): string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '') return 'pengarang';

        $map = [
            'aut' => 'pengarang',
            'author' => 'pengarang',
            'pengarang' => 'pengarang',
            'penulis' => 'pengarang',
            'edt' => 'editor',
            'editor' => 'editor',
            'penyunting' => 'editor',
            'ill' => 'ilustrator',
            'illustrator' => 'ilustrator',
            'ilustrator' => 'ilustrator',
            'trl' => 'penerjemah',
            'translator' => 'penerjemah',
            'penerjemah' => 'penerjemah',
            'pht' => 'fotografer',
            'photographer' => 'fotografer',
            'fotografer' => 'fotografer',
            'cmp' => 'komposer',
            'composer' => 'komposer',
            'komposer' => 'komposer',
            'drt' => 'sutradara',
            'director' => 'sutradara',
            'sutradara' => 'sutradara',
            'nrt' => 'narator',
            'narrator' => 'narator',
            'narator' => 'narator',
            'ctb' => 'kontributor',
            'contributor' => 'kontributor',
            'kontributor' => 'kontributor',
            'com' => 'penyusun',
            'compiler' => 'penyusun',
            'penyusun' => 'penyusun',
            'pbl' => 'penerbit',
            'publisher' => 'penerbit',
            'penerbit' => 'penerbit',
            'adp' => 'penyadur',
            'adapter' => 'penyadur',
            'penyadur' => 'penyadur',
            'cre' => 'pencipta',
            'creator' => 'pencipta',
            'pencipta' => 'pencipta',
            'prf' => 'penyaji',
            'performer' => 'penyaji',
            'penyaji' => 'penyaji',
            'act' => 'aktor',
            'actor' => 'aktor',
            'aktor' => 'aktor',
            'pro' => 'produser',
            'producer' => 'produser',
            'produser' => 'produser',
            'arr' => 'penata musik',
            'arranger' => 'penata musik',
            'penata musik' => 'penata musik',
            'dsr' => 'desainer',
            'designer' => 'desainer',
            'desainer' => 'desainer',
            'prg' => 'pemrogram',
            'programmer' => 'pemrogram',
            'pemrogram' => 'pemrogram',
            'cov' => 'ilustrator sampul',
            'cover designer' => 'ilustrator sampul',
            'ilustrator sampul' => 'ilustrator sampul',
            'rev' => 'pengulas',
            'reviewer' => 'pengulas',
            'pengulas' => 'pengulas',
            'res' => 'peneliti',
            'researcher' => 'peneliti',
            'peneliti' => 'peneliti',
            'org' => 'organisasi',
            'corporate' => 'organisasi',
            'organization' => 'organisasi',
            'organisasi' => 'organisasi',
            'lembaga' => 'organisasi',
            'instansi' => 'organisasi',
            'mtg' => 'meeting',
            'meeting' => 'meeting',
            'conference' => 'meeting',
            'seminar' => 'meeting',
            'symposium' => 'meeting',
            'simposium' => 'meeting',
            'kongres' => 'meeting',
            'konferensi' => 'meeting',
            'workshop' => 'meeting',
        ];

        if (isset($map[$v])) {
            return $map[$v];
        }

        if (strlen($v) === 3) {
            return $v; // keep unknown relator code as custom role
        }

        return $v;
    }

    private function parseIdentifiersFromMarc(\DOMXPath $xpath, \DOMNode $record): array
    {
        $result = [];
        $identifiers = [];

        $fields024 = $this->datafields($xpath, $record, '024');
        foreach ($fields024 as $field) {
            $value = $field['a'][0] ?? null;
            $scheme = $field['2'][0] ?? null;
            if (!$value) continue;
            $scheme = strtolower(trim((string) $scheme));
            if ($scheme !== '' && in_array($scheme, ['doi', 'isni', 'orcid', 'oclc', 'lccn', 'handle'], true)) {
                $result[$scheme] = $value;
            } elseif ($scheme !== '') {
                $identifiers[] = $scheme . ':' . $value;
            } else {
                $identifiers[] = $value;
            }
        }

        $fields856 = $this->datafields($xpath, $record, '856');
        foreach ($fields856 as $field) {
            $uri = $field['u'][0] ?? null;
            if ($uri) {
                if (!isset($result['uri'])) {
                    $result['uri'] = $uri;
                } else {
                    $identifiers[] = 'uri:' . $uri;
                }
            }
        }

        if (!empty($identifiers)) {
            $result['identifiers'] = implode('; ', $identifiers);
        }

        return $result;
    }

    private function controlField(\DOMXPath $xpath, \DOMNode $record, string $tag): ?string
    {
        $node = $xpath->query(".//marc:controlfield[@tag='{$tag}']", $record)->item(0);
        return $node ? trim((string) $node->textContent) : null;
    }

    private function parseLangFrom008(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) < 38) return null;
        $lang = substr($value, 35, 3);
        $lang = strtolower(trim($lang));
        return $lang !== '' ? $lang : null;
    }

    private function countCsvRows(string $path): int
    {
        $count = 0;
        if (!file_exists($path)) return 0;

        $handle = fopen($path, 'r');
        if ($handle === false) return 0;

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return 0;
        }

        while (fgetcsv($handle) !== false) {
            $count++;
        }

        fclose($handle);
        return $count;
    }

    private function countXmlRecords(string $path): int
    {
        if (!file_exists($path)) return 0;

        $reader = new \XMLReader();
        if (!$reader->open($path)) {
            return 0;
        }

        $count = 0;
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && strtolower($reader->localName) === 'record') {
                $count++;
            }
        }
        $reader->close();
        return $count;
    }
}
