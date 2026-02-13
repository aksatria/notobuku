<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Biblio;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\MarcValidationService;
use App\Services\MarcControlFieldBuilder;

class ExportService
{
    private MarcValidationService $validator;
    private MarcControlFieldBuilder $controlBuilder;

    public function __construct(
        private MetadataMappingService $mappingService,
        ?MarcValidationService $validator = null
    ) {
        $this->validator = $validator ?? new MarcValidationService();
        $this->controlBuilder = new MarcControlFieldBuilder();
    }

    public function exportCsvBiblios(int $institutionId): StreamedResponse
    {
        $count = Biblio::query()->where('institution_id', $institutionId)->count();
        $this->logExport('csv', $count);

        $headers = [
            'id',
            'title',
            'subtitle',
            'authors',
            'subjects',
            'publisher',
            'publish_year',
            'isbn',
            'language',
            'ddc',
            'call_number',
            'identifiers',
            'description',
        ];

        return response()->streamDownload(function () use ($institutionId, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            Biblio::query()
                ->where('institution_id', $institutionId)
                ->with(['authors', 'subjects', 'identifiers'])
                ->orderBy('id')
                ->chunk(200, function ($biblios) use ($handle) {
                    foreach ($biblios as $biblio) {
                        $authors = $biblio->authors?->pluck('name')->filter()->implode(', ') ?? '';
                        $subjects = $biblio->subjects?->pluck('term')->filter()->implode('; ') ?? '';
                        $description = $biblio->general_note ?? $biblio->notes ?? $biblio->ai_summary ?? null;
                        $identifiers = $biblio->identifiers?->map(fn($id) => $id->scheme . ':' . $id->value)
                            ->filter()->implode('; ') ?? '';

                        fputcsv($handle, [
                            $biblio->id,
                            $biblio->title,
                            $biblio->subtitle,
                            $authors,
                            $subjects,
                            $biblio->publisher,
                            $biblio->publish_year,
                            $biblio->isbn,
                            $biblio->language,
                            $biblio->ddc,
                            $biblio->call_number,
                            $identifiers,
                            $description,
                        ]);
                    }
                });

            fclose($handle);
        }, 'katalog.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportDublinCoreXml(int $institutionId): Response
    {
        $biblios = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'identifiers', 'metadata'])
            ->orderBy('id')
            ->get();

        $this->logExport('dcxml', $biblios->count());

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElement('records');
        $root->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $doc->appendChild($root);

        foreach ($biblios as $biblio) {
            $record = $doc->createElement('record');
            $root->appendChild($record);

            $dc = $this->mappingService->toDublinCore($biblio);
            $this->appendDcElements($doc, $record, $dc);
        }

        return response($doc->saveXML(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="katalog_dc.xml"',
        ]);
    }

    public function exportMarcXmlCore(int $institutionId): \Symfony\Component\HttpFoundation\Response
    {
        $biblios = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'identifiers'])
            ->orderBy('id')
            ->get();

        $this->logExport('marcxml', $biblios->count());
        $institutionCode = $this->resolveInstitutionCode($institutionId);
        $errors = [];
        $warnings = [];
        foreach ($biblios as $biblio) {
            $issues = $this->validator->validateForExport($biblio);
            if (!empty($issues)) {
                $hard = array_values(array_filter($issues, fn($m) => !str_starts_with((string) $m, 'WARN:')));
                $soft = array_values(array_filter($issues, fn($m) => str_starts_with((string) $m, 'WARN:')));
                if (!empty($soft)) {
                    $warnings[] = [
                        'biblio_id' => $biblio->id,
                        'title' => $biblio->title,
                        'warnings' => $soft,
                    ];
                }
                if (!empty($hard)) {
                    $errors[] = [
                        'biblio_id' => $biblio->id,
                        'title' => $biblio->title,
                        'errors' => $hard,
                    ];
                }
            }
        }
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Export MARC gagal: data belum memenuhi validasi.',
                'errors' => $errors,
            ], 422);
        }
        if (!empty($warnings)) {
            \Illuminate\Support\Facades\Log::warning('MARC export warnings.', ['warnings' => $warnings]);
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $collection = $doc->createElement('collection');
        $collection->setAttribute('xmlns', 'http://www.loc.gov/MARC21/slim');
        $doc->appendChild($collection);

        foreach ($biblios as $biblio) {
            $record = $doc->createElement('record');
            $collection->appendChild($record);

            $marc = $this->mappingService->toMarcCore($biblio);
            $this->appendMarcControlFields($doc, $record, $biblio, $institutionCode);
            $this->appendCatalogingSource($doc, $record, $biblio, $institutionCode);
            $this->appendMarcFields($doc, $record, $marc);
        }

        return response($doc->saveXML(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="katalog_marc.xml"',
        ]);
    }

    public function buildMarcPreview(array $payload, ?string $institutionCode = null): string
    {
        $biblio = new Biblio([
            'title' => $payload['title'] ?? 'Preview MARC',
            'subtitle' => $payload['subtitle'] ?? null,
            'responsibility_statement' => $payload['responsibility_statement'] ?? null,
            'place_of_publication' => $payload['place_of_publication'] ?? null,
            'publisher' => $payload['publisher'] ?? null,
            'publish_year' => $payload['publish_year'] ?? null,
            'language' => $payload['language'] ?? 'id',
            'material_type' => $payload['material_type'] ?? 'buku',
            'media_type' => $payload['media_type'] ?? 'teks',
            'isbn' => $payload['isbn'] ?? null,
            'issn' => $payload['issn'] ?? null,
        ]);
        $biblio->setAttribute('variant_title', $payload['variant_title'] ?? null);
        $biblio->setAttribute('former_title', $payload['former_title'] ?? null);
        $biblio->setAttribute('contents_note', $payload['contents_note'] ?? null);
        $biblio->setAttribute('citation_note', $payload['citation_note'] ?? null);
        $biblio->setAttribute('audience_note', $payload['audience_note'] ?? null);
        $biblio->setAttribute('language_note', $payload['language_note'] ?? null);
        $biblio->setAttribute('local_note', $payload['local_note'] ?? null);

        $authorEntries = collect();
        $meetingEntries = collect();
        if (!empty($payload['author'])) {
            $role = trim((string) ($payload['author_role'] ?? ''));
            if ($role === '') $role = 'pengarang';
            $authorEntries = $authorEntries->merge(
                collect(explode(',', (string) $payload['author']))
                    ->map(fn($x) => trim((string) $x))
                    ->filter()
                    ->values()
                    ->map(fn($name) => (object) ['name' => $name, 'role' => $role])
            );
        }

        $meetingInd1 = isset($payload['meeting_ind1']) ? (string) $payload['meeting_ind1'] : null;
        if ($meetingInd1 !== null && !in_array($meetingInd1, [' ', '0', '1', '2'], true)) {
            $meetingInd1 = null;
        }

        if (!empty($payload['meeting_names'])) {
            $meetingEntries = collect(preg_split('/[,;\n]/', (string) $payload['meeting_names']))
                ->map(fn($x) => trim((string) $x))
                ->filter()
                ->values()
                ->map(fn($name) => (object) [
                    'name' => $name,
                    'role' => 'meeting',
                    'ind1' => $meetingInd1,
                ]);
            $authorEntries = $authorEntries->merge($meetingEntries);
        }

        if (!empty($payload['force_meeting_main']) && $meetingEntries->isNotEmpty()) {
            $authorEntries = $meetingEntries->merge(
                $authorEntries->filter(fn($entry) => ($entry->role ?? null) !== 'meeting')
            );
        }

        if ($authorEntries->isNotEmpty()) {
            $biblio->setRelation('authors', $authorEntries->values());
        }

        if (!empty($payload['subjects'])) {
            $scheme = trim((string) ($payload['subject_scheme'] ?? 'local'));
            if (!in_array($scheme, ['local', 'lcsh'], true)) {
                $scheme = 'local';
            }
            $subjectType = trim((string) ($payload['subject_type'] ?? 'topic'));
            if ($subjectType === '') {
                $subjectType = 'topic';
            }
            $subjects = collect(preg_split('/[,;\n]/', (string) $payload['subjects']))
                ->map(fn($x) => trim((string) $x))
                ->filter()
                ->values()
                ->map(fn($term) => (object) ['term' => $term, 'scheme' => $scheme, 'type' => $subjectType]);
            $biblio->setRelation('subjects', $subjects);
        }

        $biblio->updated_at = now();

        $issues = $this->validator->validateForExport($biblio);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        if (!empty($issues)) {
            $doc->appendChild($doc->createComment('VALIDATION ERRORS: ' . implode(' | ', $issues)));
        }
        $record = $doc->createElement('record');
        $record->setAttribute('xmlns', 'http://www.loc.gov/MARC21/slim');
        $doc->appendChild($record);

        $marc = $this->mappingService->toMarcCore($biblio);
        $this->appendMarcControlFields($doc, $record, $biblio, $institutionCode);
        $this->appendCatalogingSource($doc, $record, $biblio, $institutionCode);
        $this->appendMarcFields($doc, $record, $marc);

        return $doc->saveXML();
    }

    public function exportJsonLd(int $institutionId): Response
    {
        $biblios = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'identifiers'])
            ->orderBy('id')
            ->get();

        $this->logExport('jsonld', $biblios->count());

        $payload = $biblios->map(function ($biblio) {
            $authors = $biblio->authors?->pluck('name')->filter()->values()->all() ?? [];
            $subjects = $biblio->subjects?->pluck('term')->filter()->values()->all() ?? [];
            $identifierNodes = $biblio->identifiers?->map(function ($id) {
                $node = [
                    '@type' => 'PropertyValue',
                    'propertyID' => $id->scheme,
                    'value' => $id->value,
                ];
                if (!empty($id->uri)) {
                    $node['url'] = $id->uri;
                }
                return $node;
            })->values()->all() ?? [];

            $authorNodes = array_map(fn($a) => ['@type' => 'Person', 'name' => $a], $authors);

            $localizedTitles = [];
            $localizedDescriptions = [];
            $i18n = $biblio->metadata?->dublin_core_i18n_json ?? null;
            if (is_array($i18n)) {
                foreach ($i18n as $locale => $payload) {
                    if (!is_array($payload)) continue;
                    if (!empty($payload['title'])) {
                        $localizedTitles[] = ['@value' => $payload['title'], '@language' => (string) $locale];
                    }
                    if (!empty($payload['description'])) {
                        $localizedDescriptions[] = ['@value' => $payload['description'], '@language' => (string) $locale];
                    }
                }
            }

            return [
                '@context' => 'https://schema.org',
                '@type' => 'Book',
                'name' => !empty($localizedTitles) ? $localizedTitles : $biblio->title,
                'alternateName' => $biblio->subtitle,
                'author' => $authorNodes,
                'publisher' => $biblio->publisher ? ['@type' => 'Organization', 'name' => $biblio->publisher] : null,
                'datePublished' => $biblio->publish_year ? (string) $biblio->publish_year : null,
                'inLanguage' => $biblio->language,
                'description' => !empty($localizedDescriptions) ? $localizedDescriptions : null,
                'isbn' => $biblio->isbn,
                'about' => $subjects,
                'identifier' => !empty($identifierNodes) ? $identifierNodes : $biblio->call_number,
            ];
        })->values()->all();

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="katalog.jsonld"',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function appendDcElements(\DOMDocument $doc, \DOMElement $record, array $dc): void
    {
        foreach ($dc as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === null || $item === '') {
                        continue;
                    }
                    $el = $doc->createElement('dc:' . $key);
                    $el->appendChild($doc->createTextNode((string) $item));
                    $record->appendChild($el);
                }
                continue;
            }

            $el = $doc->createElement('dc:' . $key);
            $el->appendChild($doc->createTextNode((string) $value));
            $record->appendChild($el);
        }
    }

    private function appendMarcFields(\DOMDocument $doc, \DOMElement $record, array $marc): void
    {
        $hasMainAuthor = !empty($marc['100']);

        foreach ($marc as $tag => $entries) {
            foreach ($entries as $entry) {
                $datafield = $doc->createElement('datafield');
                $datafield->setAttribute('tag', $tag);
                $ind1 = $entry['_ind1'] ?? $entry['ind1'] ?? null;
                $ind2 = $entry['_ind2'] ?? $entry['ind2'] ?? null;

                if ($ind1 === null) {
                    $ind1 = $tag === '245' ? ($hasMainAuthor ? '1' : '0') : ' ';
                }
                if ($ind2 === null) {
                    $ind2 = ' ';
                }

                unset($entry['_ind1'], $entry['_ind2'], $entry['ind1'], $entry['ind2']);

                $datafield->setAttribute('ind1', (string) $ind1);
                $datafield->setAttribute('ind2', (string) $ind2);

                foreach ($entry as $code => $value) {
                    $sub = $doc->createElement('subfield');
                    $sub->setAttribute('code', (string) $code);
                    $sub->appendChild($doc->createTextNode((string) $value));
                    $datafield->appendChild($sub);
                }

                $record->appendChild($datafield);
            }
        }
    }

    private function logExport(string $format, int $count): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->user()?->id,
                'action' => 'export',
                'format' => $format,
                'status' => 'success',
                'meta' => [
                    'count' => $count,
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }
    }

    private function appendMarcControlFields(
        \DOMDocument $doc,
        \DOMElement $record,
        Biblio $biblio,
        ?string $institutionCode
    ): void {
        $leader = $doc->createElement('leader', $this->buildLeader($biblio));
        $record->appendChild($leader);

        $this->appendControlField($doc, $record, '001', (string) $biblio->id);
        $this->appendControlField($doc, $record, '003', $institutionCode ?: 'NBK');
        $updatedAt = $biblio->updated_at ? $biblio->updated_at->format('YmdHis') : now()->format('YmdHis');
        $this->appendControlField($doc, $record, '005', $updatedAt);
        $controlFields = $this->controlBuilder->buildControlFields($biblio);
        $this->appendControlField($doc, $record, '006', $controlFields['006'] ?? '');
        $this->appendControlField($doc, $record, '007', $controlFields['007'] ?? '');
        $this->appendControlField($doc, $record, '008', $controlFields['008'] ?? '');
    }

    private function appendControlField(\DOMDocument $doc, \DOMElement $record, string $tag, string $value): void
    {
        $control = $doc->createElement('controlfield');
        $control->setAttribute('tag', $tag);
        $control->appendChild($doc->createTextNode($value));
        $record->appendChild($control);
    }

    private function appendCatalogingSource(
        \DOMDocument $doc,
        \DOMElement $record,
        Biblio $biblio,
        ?string $institutionCode
    ): void {
        $code = $institutionCode ?: 'NBK';
        $lang = $this->normalizeLangCode($biblio->language);

        $datafield = $doc->createElement('datafield');
        $datafield->setAttribute('tag', '040');
        $datafield->setAttribute('ind1', ' ');
        $datafield->setAttribute('ind2', ' ');

        $subA = $doc->createElement('subfield');
        $subA->setAttribute('code', 'a');
        $subA->appendChild($doc->createTextNode($code));
        $datafield->appendChild($subA);

        $subB = $doc->createElement('subfield');
        $subB->setAttribute('code', 'b');
        $subB->appendChild($doc->createTextNode($lang));
        $datafield->appendChild($subB);

        $subC = $doc->createElement('subfield');
        $subC->setAttribute('code', 'c');
        $subC->appendChild($doc->createTextNode($code));
        $datafield->appendChild($subC);

        $record->appendChild($datafield);
    }

    private function buildLeader(Biblio $biblio): string
    {
        return $this->controlBuilder->buildLeader($biblio);
    }

    private function normalizeLangCode(?string $value): string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '') return 'und';

        $map = [
            'id' => 'ind',
            'in' => 'ind',
            'en' => 'eng',
            'fr' => 'fre',
            'de' => 'ger',
            'es' => 'spa',
            'ar' => 'ara',
            'zh' => 'chi',
            'ja' => 'jpn',
            'ko' => 'kor',
            'ru' => 'rus',
            'nl' => 'dut',
            'it' => 'ita',
            'pt' => 'por',
            'ms' => 'msa',
        ];

        if (isset($map[$v])) return $map[$v];
        if (strlen($v) === 3) return $v;
        if (strlen($v) > 3) return substr($v, 0, 3);
        return 'und';
    }

    private function resolveInstitutionCode(int $institutionId): ?string
    {
        try {
            return DB::table('institutions')->where('id', $institutionId)->value('code');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
