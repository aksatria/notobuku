<?php

namespace App\Http\Controllers;

use App\Support\InteropMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OaiPmhController extends Controller
{
    private const REPO_ID = 'notobuku';
    private const METADATA_PREFIX_DC = 'oai_dc';
    private const METADATA_PREFIX_MARC = 'oai_marc';
    private const PAGE_SIZE = 100;
    private const SNAPSHOT_TTL_MINUTES = 30;
    private const MAX_SNAPSHOTS_PER_CLIENT = 5;

    public function handle(Request $request)
    {
        $startedAt = microtime(true);
        try {
            $verb = trim((string) $request->query('verb', ''));
            $allowed = ['Identify', 'ListMetadataFormats', 'ListSets', 'ListIdentifiers', 'ListRecords', 'GetRecord'];
            if (!in_array($verb, $allowed, true)) {
                return $this->errorResponse('badVerb', 'Verb tidak didukung.', $request, $verb !== '' ? ['verb' => $verb] : []);
            }

            return match ($verb) {
                'Identify' => $this->identifyResponse($request),
                'ListMetadataFormats' => $this->listMetadataFormatsResponse($request),
                'ListSets' => $this->listSetsResponse($request),
                'ListIdentifiers' => $this->listIdentifiersResponse($request),
                'ListRecords' => $this->listRecordsResponse($request),
                'GetRecord' => $this->getRecordResponse($request),
            };
        } finally {
            InteropMetrics::recordLatency('oai', (microtime(true) - $startedAt) * 1000);
        }
    }

    private function identifyResponse(Request $request)
    {
        $doc = $this->createEnvelope($request, 'Identify', []);
        $root = $doc->documentElement;
        $identify = $doc->createElement('Identify');
        $root->appendChild($identify);

        $earliest = DB::table('biblio')->min('updated_at');
        $earliestDatestamp = $earliest ? $this->toUtcDateTime((string) $earliest) : now()->utc()->format('Y-m-d\TH:i:s\Z');

        $this->appendNode($doc, $identify, 'repositoryName', config('app.name', 'NOTOBUKU'));
        $this->appendNode($doc, $identify, 'baseURL', route('oai.pmh', [], false));
        $this->appendNode($doc, $identify, 'protocolVersion', '2.0');
        $this->appendNode($doc, $identify, 'adminEmail', (string) config('mail.from.address', 'admin@localhost'));
        $this->appendNode($doc, $identify, 'earliestDatestamp', $earliestDatestamp);
        $this->appendNode($doc, $identify, 'deletedRecord', 'transient');
        $this->appendNode($doc, $identify, 'granularity', 'YYYY-MM-DDThh:mm:ssZ');

        return $this->xmlResponse($doc);
    }

    private function listMetadataFormatsResponse(Request $request)
    {
        $doc = $this->createEnvelope($request, 'ListMetadataFormats', []);
        $root = $doc->documentElement;
        $formats = $doc->createElement('ListMetadataFormats');
        $root->appendChild($formats);

        $item = $doc->createElement('metadataFormat');
        $formats->appendChild($item);
        $this->appendNode($doc, $item, 'metadataPrefix', self::METADATA_PREFIX_DC);
        $this->appendNode($doc, $item, 'schema', 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd');
        $this->appendNode($doc, $item, 'metadataNamespace', 'http://www.openarchives.org/OAI/2.0/oai_dc/');

        $itemMarc = $doc->createElement('metadataFormat');
        $formats->appendChild($itemMarc);
        $this->appendNode($doc, $itemMarc, 'metadataPrefix', self::METADATA_PREFIX_MARC);
        $this->appendNode($doc, $itemMarc, 'schema', 'http://www.openarchives.org/OAI/1.1/oai_marc.xsd');
        $this->appendNode($doc, $itemMarc, 'metadataNamespace', 'http://www.openarchives.org/OAI/1.1/oai_marc');

        return $this->xmlResponse($doc);
    }

    private function listSetsResponse(Request $request)
    {
        $doc = $this->createEnvelope($request, 'ListSets', []);
        $root = $doc->documentElement;
        $list = $doc->createElement('ListSets');
        $root->appendChild($list);

        $sets = DB::table('institutions')
            ->orderBy('id')
            ->get(['id', 'code', 'name']);

        if ($sets->isEmpty()) {
            return $this->errorResponse('noSetHierarchy', 'Tidak ada set yang tersedia.', $request, []);
        }

        foreach ($sets as $set) {
            $setNode = $doc->createElement('set');
            $list->appendChild($setNode);
            $this->appendNode($doc, $setNode, 'setSpec', $this->institutionSetSpec((int) $set->id));
            $name = trim((string) ($set->name ?? ''));
            $code = trim((string) ($set->code ?? ''));
            $display = $name !== '' ? $name : ('Institution #' . (int) $set->id);
            if ($code !== '') {
                $display .= ' (' . $code . ')';
            }
            $this->appendNode($doc, $setNode, 'setName', $display);
        }

        return $this->xmlResponse($doc);
    }

    private function listIdentifiersResponse(Request $request)
    {
        $state = $this->resolveHarvestState($request);
        if (($state['ok'] ?? false) !== true) {
            return $this->errorResponse(
                (string) ($state['error_code'] ?? 'badArgument'),
                (string) ($state['error_message'] ?? 'Parameter tidak valid.'),
                $request,
                (array) ($state['request_params'] ?? [])
            );
        }

        $harvest = $this->loadHarvestRows((array) $state, false);
        $rows = (array) ($harvest['rows'] ?? []);
        $total = (int) ($harvest['total'] ?? 0);
        $state['snapshot_key'] = (string) ($harvest['snapshot_key'] ?? ($state['snapshot_key'] ?? ''));
        if (count($rows) === 0) {
            return $this->errorResponse('noRecordsMatch', 'Tidak ada record sesuai filter.', $request, (array) $state['request_params']);
        }

        $doc = $this->createEnvelope($request, 'ListIdentifiers', (array) $state['request_params']);
        $root = $doc->documentElement;
        $list = $doc->createElement('ListIdentifiers');
        $root->appendChild($list);

        foreach ($rows as $row) {
            $header = $doc->createElement('header');
            if (!empty($row['is_deleted'])) {
                $header->setAttribute('status', 'deleted');
            }
            $list->appendChild($header);
            $this->appendNode($doc, $header, 'identifier', $this->oaiIdentifier((int) ($row['id'] ?? 0)));
            $this->appendNode($doc, $header, 'datestamp', (string) ($row['datestamp'] ?? now()->utc()->format('Y-m-d\TH:i:s\Z')));
            $setSpec = $this->institutionSetSpec((int) ($row['institution_id'] ?? 0));
            if ($setSpec !== null) {
                $this->appendNode($doc, $header, 'setSpec', $setSpec);
            }
        }

        $nextOffset = (int) $state['offset'] + count($rows);
        if ($nextOffset < $total) {
            $this->appendResumptionToken($doc, $list, (array) $state, $nextOffset, $total);
        }

        return $this->xmlResponse($doc);
    }

    private function listRecordsResponse(Request $request)
    {
        $state = $this->resolveHarvestState($request);
        if (($state['ok'] ?? false) !== true) {
            return $this->errorResponse(
                (string) ($state['error_code'] ?? 'badArgument'),
                (string) ($state['error_message'] ?? 'Parameter tidak valid.'),
                $request,
                (array) ($state['request_params'] ?? [])
            );
        }

        $harvest = $this->loadHarvestRows((array) $state, true);
        $rows = (array) ($harvest['rows'] ?? []);
        $total = (int) ($harvest['total'] ?? 0);
        $state['snapshot_key'] = (string) ($harvest['snapshot_key'] ?? ($state['snapshot_key'] ?? ''));
        if (count($rows) === 0) {
            return $this->errorResponse('noRecordsMatch', 'Tidak ada record sesuai filter.', $request, (array) $state['request_params']);
        }

        $doc = $this->createEnvelope($request, 'ListRecords', (array) $state['request_params']);
        $root = $doc->documentElement;
        $list = $doc->createElement('ListRecords');
        $root->appendChild($list);

        foreach ($rows as $row) {
            $record = $doc->createElement('record');
            $list->appendChild($record);

            $header = $doc->createElement('header');
            if (!empty($row['is_deleted'])) {
                $header->setAttribute('status', 'deleted');
            }
            $record->appendChild($header);
            $this->appendNode($doc, $header, 'identifier', $this->oaiIdentifier((int) ($row['id'] ?? 0)));
            $this->appendNode($doc, $header, 'datestamp', (string) ($row['datestamp'] ?? now()->utc()->format('Y-m-d\TH:i:s\Z')));
            $setSpec = $this->institutionSetSpec((int) ($row['institution_id'] ?? 0));
            if ($setSpec !== null) {
                $this->appendNode($doc, $header, 'setSpec', $setSpec);
            }

            if (empty($row['is_deleted'])) {
                $metadata = $doc->createElement('metadata');
                $record->appendChild($metadata);
                $metadata->appendChild($this->buildMetadataNode($doc, (object) $row, (string) $state['metadataPrefix']));
            }
        }

        $nextOffset = (int) $state['offset'] + count($rows);
        if ($nextOffset < $total) {
            $this->appendResumptionToken($doc, $list, (array) $state, $nextOffset, $total);
        }

        return $this->xmlResponse($doc);
    }

    private function getRecordResponse(Request $request)
    {
        $identifier = trim((string) $request->query('identifier', ''));
        $metadataPrefix = trim((string) $request->query('metadataPrefix', ''));
        if ($identifier === '' || $metadataPrefix === '') {
            return $this->errorResponse('badArgument', 'Parameter identifier dan metadataPrefix wajib.', $request, [
                'identifier' => $identifier,
                'metadataPrefix' => $metadataPrefix,
            ]);
        }
        if (!in_array($metadataPrefix, [self::METADATA_PREFIX_DC, self::METADATA_PREFIX_MARC], true)) {
            return $this->errorResponse('cannotDisseminateFormat', 'metadataPrefix tidak didukung.', $request, ['metadataPrefix' => $metadataPrefix]);
        }

        $id = $this->parseOaiIdentifier($identifier);
        if ($id <= 0) {
            return $this->errorResponse('idDoesNotExist', 'identifier tidak ditemukan.', $request, ['identifier' => $identifier]);
        }

        $row = DB::table('biblio as b')
            ->leftJoin('biblio_metadata as bm', 'bm.biblio_id', '=', 'b.id')
            ->where('b.id', $id)
            ->select([
                'b.id',
                'b.title',
                'b.subtitle',
                'b.publisher',
                'b.publish_year',
                'b.language',
                'b.material_type',
                'b.isbn',
                'b.issn',
                'b.notes',
                'b.updated_at',
                'bm.dublin_core_json',
            ])
            ->first();

        if (!$row) {
            $tombstone = $this->loadTombstoneByBiblioId($id);
            if (!$tombstone) {
                return $this->errorResponse('idDoesNotExist', 'identifier tidak ditemukan.', $request, ['identifier' => $identifier]);
            }

            $doc = $this->createEnvelope($request, 'GetRecord', [
                'identifier' => $identifier,
                'metadataPrefix' => $metadataPrefix,
            ]);
            $root = $doc->documentElement;
            $getRecord = $doc->createElement('GetRecord');
            $root->appendChild($getRecord);
            $record = $doc->createElement('record');
            $getRecord->appendChild($record);
            $header = $doc->createElement('header');
            $header->setAttribute('status', 'deleted');
            $record->appendChild($header);
            $this->appendNode($doc, $header, 'identifier', $this->oaiIdentifier($id));
            $this->appendNode($doc, $header, 'datestamp', (string) ($tombstone['datestamp'] ?? now()->utc()->format('Y-m-d\TH:i:s\Z')));
            $setSpec = $this->institutionSetSpec((int) ($tombstone['institution_id'] ?? 0));
            if ($setSpec !== null) {
                $this->appendNode($doc, $header, 'setSpec', $setSpec);
            }
            return $this->xmlResponse($doc);
        }

        $doc = $this->createEnvelope($request, 'GetRecord', [
            'identifier' => $identifier,
            'metadataPrefix' => $metadataPrefix,
        ]);
        $root = $doc->documentElement;
        $getRecord = $doc->createElement('GetRecord');
        $root->appendChild($getRecord);

        $record = $doc->createElement('record');
        $getRecord->appendChild($record);

        $header = $doc->createElement('header');
        $record->appendChild($header);
        $this->appendNode($doc, $header, 'identifier', $this->oaiIdentifier((int) $row->id));
        $this->appendNode($doc, $header, 'datestamp', $this->toUtcDateTime((string) $row->updated_at));

        $metadata = $doc->createElement('metadata');
        $record->appendChild($metadata);
        $metadata->appendChild($this->buildMetadataNode($doc, $row, $metadataPrefix));

        return $this->xmlResponse($doc);
    }

    private function buildMetadataNode(\DOMDocument $doc, object $row, string $metadataPrefix): \DOMElement
    {
        return $metadataPrefix === self::METADATA_PREFIX_MARC
            ? $this->buildOaiMarcNode($doc, $row)
            : $this->buildOaiDcNode($doc, $row);
    }

    private function buildOaiDcNode(\DOMDocument $doc, object $row): \DOMElement
    {
        $dc = $doc->createElementNS('http://www.openarchives.org/OAI/2.0/oai_dc/', 'oai_dc:dc');
        $dc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $dc->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd');

        $dcJson = json_decode((string) ($row->dublin_core_json ?? ''), true);
        if (!is_array($dcJson)) {
            $dcJson = [];
        }

        $title = (string) ($row->title ?? '');
        if ((string) ($row->subtitle ?? '') !== '') {
            $title .= ': ' . (string) $row->subtitle;
        }
        $this->appendDcMulti($doc, $dc, 'title', $dcJson['title'] ?? $title);
        $this->appendDcMulti($doc, $dc, 'creator', $dcJson['creator'] ?? []);
        $this->appendDcMulti($doc, $dc, 'subject', $dcJson['subject'] ?? []);
        $this->appendDcMulti($doc, $dc, 'description', $dcJson['description'] ?? ((string) ($row->notes ?? '')));
        $this->appendDcMulti($doc, $dc, 'publisher', $dcJson['publisher'] ?? ((string) ($row->publisher ?? '')));
        $this->appendDcMulti($doc, $dc, 'date', $dcJson['date'] ?? ((string) ($row->publish_year ?? '')));
        $this->appendDcMulti($doc, $dc, 'type', $dcJson['type'] ?? ((string) ($row->material_type ?? 'text')));
        $this->appendDcMulti($doc, $dc, 'language', $dcJson['language'] ?? ((string) ($row->language ?? 'id')));

        $identifiers = $dcJson['identifier'] ?? [];
        $identifiers = is_array($identifiers) ? $identifiers : [$identifiers];
        $isbn = trim((string) ($row->isbn ?? ''));
        if ($isbn !== '') {
            $identifiers[] = 'ISBN ' . $isbn;
        }
        $issn = trim((string) ($row->issn ?? ''));
        if ($issn !== '') {
            $identifiers[] = 'ISSN ' . $issn;
        }
        $identifiers[] = $this->oaiIdentifier((int) $row->id);
        $this->appendDcMulti($doc, $dc, 'identifier', $identifiers);

        $this->appendDcMulti($doc, $dc, 'format', $dcJson['format'] ?? []);
        $this->appendDcMulti($doc, $dc, 'source', $dcJson['source'] ?? []);
        $this->appendDcMulti($doc, $dc, 'relation', $dcJson['relation'] ?? []);
        $this->appendDcMulti($doc, $dc, 'coverage', $dcJson['coverage'] ?? []);
        $this->appendDcMulti($doc, $dc, 'rights', $dcJson['rights'] ?? []);

        return $dc;
    }

    private function buildOaiMarcNode(\DOMDocument $doc, object $row): \DOMElement
    {
        $marc = $doc->createElementNS('http://www.openarchives.org/OAI/1.1/oai_marc', 'oai_marc:record');
        $marc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:oai_marc', 'http://www.openarchives.org/OAI/1.1/oai_marc');
        $marc->setAttribute('type', 'Bibliographic');
        $marc->setAttribute('level', 'Monograph');

        $title = trim((string) ($row->title ?? ''));
        $subtitle = trim((string) ($row->subtitle ?? ''));
        if ($subtitle !== '') {
            $title .= ': ' . $subtitle;
        }
        $this->appendOaiMarcVarfield($doc, $marc, '245', [
            ['code' => 'a', 'value' => $title],
        ]);
        $this->appendOaiMarcVarfield($doc, $marc, '260', [
            ['code' => 'b', 'value' => (string) ($row->publisher ?? '')],
            ['code' => 'c', 'value' => (string) ($row->publish_year ?? '')],
        ]);
        $this->appendOaiMarcVarfield($doc, $marc, '020', [
            ['code' => 'a', 'value' => (string) ($row->isbn ?? '')],
        ]);
        $this->appendOaiMarcVarfield($doc, $marc, '022', [
            ['code' => 'a', 'value' => (string) ($row->issn ?? '')],
        ]);
        $this->appendOaiMarcVarfield($doc, $marc, '041', [
            ['code' => 'a', 'value' => (string) ($row->language ?? 'id')],
        ]);

        $dcJson = json_decode((string) ($row->dublin_core_json ?? ''), true);
        if (is_array($dcJson)) {
            $creators = $dcJson['creator'] ?? [];
            $creators = is_array($creators) ? $creators : [$creators];
            foreach ($creators as $creator) {
                $this->appendOaiMarcVarfield($doc, $marc, '100', [
                    ['code' => 'a', 'value' => (string) $creator],
                ]);
            }
            $subjects = $dcJson['subject'] ?? [];
            $subjects = is_array($subjects) ? $subjects : [$subjects];
            foreach ($subjects as $subject) {
                $this->appendOaiMarcVarfield($doc, $marc, '650', [
                    ['code' => 'a', 'value' => (string) $subject],
                ]);
            }
        }

        return $marc;
    }

    private function appendOaiMarcVarfield(\DOMDocument $doc, \DOMElement $record, string $tag, array $subfields): void
    {
        $filtered = [];
        foreach ($subfields as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($code === '' || $value === '') {
                continue;
            }
            $filtered[] = ['code' => $code, 'value' => $value];
        }
        if (count($filtered) === 0) {
            return;
        }

        $varfield = $doc->createElementNS('http://www.openarchives.org/OAI/1.1/oai_marc', 'oai_marc:varfield');
        $varfield->setAttribute('id', $tag);
        foreach ($filtered as $sf) {
            $sub = $doc->createElementNS('http://www.openarchives.org/OAI/1.1/oai_marc', 'oai_marc:subfield');
            $sub->setAttribute('label', (string) $sf['code']);
            $sub->appendChild($doc->createTextNode((string) $sf['value']));
            $varfield->appendChild($sub);
        }
        $record->appendChild($varfield);
    }

    private function appendDcMulti(\DOMDocument $doc, \DOMElement $parent, string $name, mixed $value): void
    {
        $items = is_array($value) ? $value : [$value];
        foreach ($items as $item) {
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            $node = $doc->createElementNS('http://purl.org/dc/elements/1.1/', 'dc:' . $name);
            $node->appendChild($doc->createTextNode($text));
            $parent->appendChild($node);
        }
    }

    private function resolveHarvestState(Request $request): array
    {
        $resumptionToken = trim((string) $request->query('resumptionToken', ''));
        $metadataPrefix = trim((string) $request->query('metadataPrefix', ''));
        $fromInput = trim((string) $request->query('from', ''));
        $untilInput = trim((string) $request->query('until', ''));
        $setInput = trim((string) $request->query('set', ''));

        $offset = 0;
        $from = null;
        $until = null;
        $setId = null;
        $fromGranularity = '';
        $untilGranularity = '';
        $snapshotKey = '';
        $clientKey = $this->buildClientFingerprint($request);

        if ($resumptionToken !== '') {
            $parsed = $this->parseResumptionToken($resumptionToken);
            if ($parsed === null) {
                return $this->badResumptionTokenState($resumptionToken);
            }
            $metadataPrefix = (string) ($parsed['metadataPrefix'] ?? '');
            $snapshotKey = trim((string) ($parsed['snapshot_key'] ?? ''));
            if ($snapshotKey === '') {
                return $this->badResumptionTokenState($resumptionToken);
            }
            $snapshot = $this->loadHarvestSnapshot($snapshotKey);
            if ($snapshot === null) {
                return $this->badResumptionTokenState($resumptionToken);
            }
            $snapshotClientKey = (string) ($snapshot['client_key'] ?? '');
            if ($snapshotClientKey !== '' && $snapshotClientKey !== $clientKey) {
                return $this->badResumptionTokenState($resumptionToken);
            }
            $fromRaw = trim((string) ($snapshot['from'] ?? ''));
            $untilRaw = trim((string) ($snapshot['until'] ?? ''));
            $setRaw = trim((string) ($snapshot['set'] ?? ''));
            $fromGranularity = trim((string) ($snapshot['from_granularity'] ?? ''));
            $untilGranularity = trim((string) ($snapshot['until_granularity'] ?? ''));
            if (!$this->isValidGranularityToken($fromGranularity, $untilGranularity)) {
                return $this->badResumptionTokenState($resumptionToken);
            }
            $from = $fromRaw !== '' ? $this->parseOaiDate($fromRaw, false) : null;
            $until = $untilRaw !== '' ? $this->parseOaiDate($untilRaw, true) : null;
            $setId = $setRaw !== '' ? $this->parseInstitutionSetSpec($setRaw) : null;
            $offset = (int) ($parsed['offset'] ?? 0);
        } else {
            if ($metadataPrefix === '') {
                return [
                    'ok' => false,
                    'error_code' => 'badArgument',
                    'error_message' => 'Parameter metadataPrefix wajib.',
                    'request_params' => ['metadataPrefix' => $metadataPrefix],
                ];
            }
            $from = $fromInput !== '' ? $this->parseOaiDate($fromInput, false) : null;
            $until = $untilInput !== '' ? $this->parseOaiDate($untilInput, true) : null;
            $fromGranularity = $this->detectGranularity($fromInput);
            $untilGranularity = $this->detectGranularity($untilInput);
            if (($fromInput !== '' && $from === null) || ($untilInput !== '' && $until === null)) {
                return [
                    'ok' => false,
                    'error_code' => 'badArgument',
                    'error_message' => 'Format from/until tidak valid.',
                    'request_params' => [
                        'metadataPrefix' => $metadataPrefix,
                        'from' => $fromInput,
                        'until' => $untilInput,
                        'set' => $setInput,
                    ],
                ];
            }
            if ($fromInput !== '' && $fromGranularity === '') {
                return [
                    'ok' => false,
                    'error_code' => 'badArgument',
                    'error_message' => 'Format from tidak valid.',
                    'request_params' => [
                        'metadataPrefix' => $metadataPrefix,
                        'from' => $fromInput,
                        'until' => $untilInput,
                    ],
                ];
            }
            if ($untilInput !== '' && $untilGranularity === '') {
                return [
                    'ok' => false,
                    'error_code' => 'badArgument',
                    'error_message' => 'Format until tidak valid.',
                    'request_params' => [
                        'metadataPrefix' => $metadataPrefix,
                        'from' => $fromInput,
                        'until' => $untilInput,
                    ],
                ];
            }
            if ($fromInput !== '' && $untilInput !== '' && $fromGranularity !== '' && $untilGranularity !== '' && $fromGranularity !== $untilGranularity) {
                return [
                    'ok' => false,
                    'error_code' => 'badArgument',
                    'error_message' => 'Granularity from/until harus konsisten (day vs datetime).',
                    'request_params' => [
                        'metadataPrefix' => $metadataPrefix,
                        'from' => $fromInput,
                        'until' => $untilInput,
                    ],
                ];
            }
            if ($from && $until && $from->gt($until)) {
                return [
                    'ok' => false,
                    'error_code' => 'badArgument',
                    'error_message' => 'Nilai from tidak boleh lebih besar dari until.',
                    'request_params' => [
                        'metadataPrefix' => $metadataPrefix,
                        'from' => $fromInput,
                        'until' => $untilInput,
                        'set' => $setInput,
                    ],
                ];
            }
            if ($setInput !== '') {
                $setId = $this->parseInstitutionSetSpec($setInput);
                if ($setId <= 0) {
                    return [
                        'ok' => false,
                        'error_code' => 'badArgument',
                        'error_message' => 'Format set tidak valid.',
                        'request_params' => [
                            'metadataPrefix' => $metadataPrefix,
                            'set' => $setInput,
                        ],
                    ];
                }
                $exists = DB::table('institutions')->where('id', $setId)->exists();
                if (!$exists) {
                    return [
                        'ok' => false,
                        'error_code' => 'noRecordsMatch',
                        'error_message' => 'Set tidak ditemukan.',
                        'request_params' => [
                            'metadataPrefix' => $metadataPrefix,
                            'set' => $setInput,
                        ],
                    ];
                }
            }
        }

        if (!in_array($metadataPrefix, [self::METADATA_PREFIX_DC, self::METADATA_PREFIX_MARC], true)) {
            return [
                'ok' => false,
                'error_code' => 'cannotDisseminateFormat',
                'error_message' => 'metadataPrefix tidak didukung.',
                'request_params' => ['metadataPrefix' => $metadataPrefix],
            ];
        }

        $requestParams = [
            'metadataPrefix' => $metadataPrefix,
            'from' => $fromInput,
            'until' => $untilInput,
            'set' => $setInput,
            'resumptionToken' => $resumptionToken,
        ];
        if ($resumptionToken !== '') {
            $requestParams = ['resumptionToken' => $resumptionToken];
        }

        return [
            'ok' => true,
            'metadataPrefix' => $metadataPrefix,
            'from' => $from,
            'until' => $until,
            'from_granularity' => $fromGranularity,
            'until_granularity' => $untilGranularity,
            'set_id' => $setId,
            'set_spec' => $setId ? $this->institutionSetSpec($setId) : null,
            'snapshot_key' => $snapshotKey,
            'client_key' => $clientKey,
            'offset' => $offset,
            'request_params' => $requestParams,
        ];
    }

    private function buildHarvestQuery(array $state): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('biblio as b')
            ->leftJoin('biblio_metadata as bm', 'bm.biblio_id', '=', 'b.id')
            ->select([
                'b.id',
                'b.institution_id',
                'b.title',
                'b.subtitle',
                'b.publisher',
                'b.publish_year',
                'b.language',
                'b.material_type',
                'b.isbn',
                'b.issn',
                'b.notes',
                'b.updated_at',
                'bm.dublin_core_json',
            ]);

        $from = $state['from'] ?? null;
        $until = $state['until'] ?? null;
        $setId = (int) ($state['set_id'] ?? 0);

        if ($from) {
            $query->where('b.updated_at', '>=', $from);
        }
        if ($until) {
            $query->where('b.updated_at', '<=', $until);
        }
        if ($setId > 0) {
            $query->where('b.institution_id', $setId);
        }

        return $query;
    }

    private function loadHarvestRows(array $state, bool $includeMetadata = false): array
    {
        $snapshotKey = trim((string) ($state['snapshot_key'] ?? ''));
        $snapshot = $snapshotKey !== '' ? $this->loadHarvestSnapshot($snapshotKey) : null;
        if ($snapshot === null) {
            $snapshot = $this->createHarvestSnapshot($state);
            $snapshotKey = (string) ($snapshot['snapshot_key'] ?? '');
            $state['snapshot_key'] = $snapshotKey;
        }

        $all = (array) ($snapshot['rows'] ?? []);
        $total = count($all);
        $offset = max(0, (int) ($state['offset'] ?? 0));
        $rows = array_slice($all, $offset, self::PAGE_SIZE);
        if ($includeMetadata) {
            $rows = $this->hydrateActiveRowsForRecords($rows);
        }

        return [
            'snapshot_key' => $snapshotKey,
            'total' => $total,
            'rows' => $rows,
        ];
    }

    private function createHarvestSnapshot(array $state): array
    {
        $activeRows = $this->buildHarvestQuery($state)
            ->orderBy('b.updated_at')
            ->orderBy('b.id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) ($row->id ?? 0),
                    'institution_id' => (int) ($row->institution_id ?? 0),
                    'datestamp' => $this->toUtcDateTime((string) ($row->updated_at ?? '')),
                    'is_deleted' => false,
                ];
            })
            ->all();

        $activeIdMap = [];
        foreach ($activeRows as $r) {
            $activeIdMap[(int) ($r['id'] ?? 0)] = true;
        }
        $deletedRows = array_values(array_filter($this->loadTombstoneRows($state), function (array $row) use ($activeIdMap) {
            $id = (int) ($row['id'] ?? 0);
            return $id > 0 && !isset($activeIdMap[$id]);
        }));

        $all = array_merge($activeRows, $deletedRows);
        usort($all, function (array $a, array $b): int {
            $da = (string) ($a['datestamp'] ?? '');
            $db = (string) ($b['datestamp'] ?? '');
            if ($da === $db) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            return strcmp($da, $db);
        });

        $snapshotId = Str::random(32);
        $snapshotKey = $this->snapshotCacheKey($snapshotId);
        $snapshot = [
            'snapshot_key' => $snapshotId,
            'metadataPrefix' => (string) ($state['metadataPrefix'] ?? self::METADATA_PREFIX_DC),
            'from' => !empty($state['from']) ? $state['from']->toIso8601String() : '',
            'until' => !empty($state['until']) ? $state['until']->toIso8601String() : '',
            'from_granularity' => (string) ($state['from_granularity'] ?? ''),
            'until_granularity' => (string) ($state['until_granularity'] ?? ''),
            'set' => (string) ($state['set_spec'] ?? ''),
            'client_key' => (string) ($state['client_key'] ?? ''),
            'rows' => $all,
            'generated_at' => now()->toIso8601String(),
        ];
        Cache::put($snapshotKey, $snapshot, now()->addMinutes(self::SNAPSHOT_TTL_MINUTES));
        $this->registerSnapshotForClient($snapshotId, (string) ($state['client_key'] ?? ''));

        return $snapshot;
    }

    private function loadHarvestSnapshot(string $snapshotId): ?array
    {
        if (trim($snapshotId) === '') {
            return null;
        }
        $val = Cache::get($this->snapshotCacheKey($snapshotId));
        return is_array($val) ? $val : null;
    }

    private function snapshotCacheKey(string $snapshotId): string
    {
        return 'oai:harvest:snapshot:' . $snapshotId;
    }

    private function snapshotClientListCacheKey(string $clientKey): string
    {
        return 'oai:harvest:snapshots:client:' . $clientKey;
    }

    private function registerSnapshotForClient(string $snapshotId, string $clientKey): void
    {
        $snapshotId = trim($snapshotId);
        $clientKey = trim($clientKey);
        if ($snapshotId === '' || $clientKey === '') {
            return;
        }

        $listKey = $this->snapshotClientListCacheKey($clientKey);
        $list = Cache::get($listKey, []);
        if (!is_array($list)) {
            $list = [];
        }

        $ids = [$snapshotId];
        foreach ($list as $entry) {
            $id = trim((string) ($entry['id'] ?? $entry));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        $ordered = [];
        $seen = [];
        foreach ($ids as $id) {
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ordered[] = $id;
        }

        $keep = array_slice($ordered, 0, self::MAX_SNAPSHOTS_PER_CLIENT);
        $evict = array_slice($ordered, self::MAX_SNAPSHOTS_PER_CLIENT);

        $newList = array_map(fn($id) => ['id' => $id], $keep);
        Cache::put($listKey, $newList, now()->addMinutes(self::SNAPSHOT_TTL_MINUTES));

        foreach ($evict as $id) {
            Cache::forget($this->snapshotCacheKey((string) $id));
        }
        if (count($evict) > 0) {
            InteropMetrics::incrementSnapshotEvictions(count($evict));
        }
    }

    private function badResumptionTokenState(string $resumptionToken): array
    {
        InteropMetrics::incrementInvalidToken('oai');

        return [
            'ok' => false,
            'error_code' => 'badResumptionToken',
            'error_message' => 'resumptionToken tidak valid.',
            'request_params' => ['resumptionToken' => $resumptionToken],
        ];
    }

    private function buildClientFingerprint(Request $request): string
    {
        $ip = trim((string) $request->ip());
        $ua = trim((string) $request->userAgent());
        return hash('sha256', $ip . '|' . $ua);
    }

    private function tokenSigningKey(): string
    {
        $key = (string) config('app.key', '');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        return $key !== '' ? $key : 'notobuku-default-token-key';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($value, true);
        return is_string($decoded) ? $decoded : '';
    }

    private function hydrateActiveRowsForRecords(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (empty($row['is_deleted'])) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) === 0) {
            return $rows;
        }

        $detailRows = DB::table('biblio as b')
            ->leftJoin('biblio_metadata as bm', 'bm.biblio_id', '=', 'b.id')
            ->whereIn('b.id', $ids)
            ->select([
                'b.id',
                'b.title',
                'b.subtitle',
                'b.publisher',
                'b.publish_year',
                'b.language',
                'b.material_type',
                'b.isbn',
                'b.issn',
                'b.notes',
                'bm.dublin_core_json',
            ])
            ->get();

        $map = [];
        foreach ($detailRows as $row) {
            $map[(int) $row->id] = [
                'title' => (string) ($row->title ?? ''),
                'subtitle' => (string) ($row->subtitle ?? ''),
                'publisher' => (string) ($row->publisher ?? ''),
                'publish_year' => $row->publish_year,
                'language' => (string) ($row->language ?? ''),
                'material_type' => (string) ($row->material_type ?? ''),
                'isbn' => (string) ($row->isbn ?? ''),
                'issn' => (string) ($row->issn ?? ''),
                'notes' => (string) ($row->notes ?? ''),
                'dublin_core_json' => $row->dublin_core_json,
            ];
        }

        foreach ($rows as &$row) {
            if (!empty($row['is_deleted'])) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && isset($map[$id])) {
                $row = array_merge($row, $map[$id]);
            }
        }
        unset($row);

        return $rows;
    }

    private function loadTombstoneRows(array $state): array
    {
        $query = DB::table('audit_logs')
            ->where('action', 'delete')
            ->where('format', 'biblio')
            ->select(['meta', 'created_at']);

        $from = $state['from'] ?? null;
        $until = $state['until'] ?? null;
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($until) {
            $query->where('created_at', '<=', $until);
        }

        $setId = (int) ($state['set_id'] ?? 0);
        if ($setId > 0) {
            $query->where('meta->institution_id', $setId);
        }

        return $query->orderBy('created_at')->get()->map(function ($row) {
            $meta = is_array($row->meta) ? $row->meta : (json_decode((string) ($row->meta ?? '{}'), true) ?: []);
            $id = (int) ($meta['biblio_id'] ?? 0);
            if ($id <= 0) {
                return null;
            }
            return [
                'id' => $id,
                'institution_id' => (int) ($meta['institution_id'] ?? 0),
                'datestamp' => $this->toUtcDateTime((string) ($row->created_at ?? now())),
                'is_deleted' => true,
            ];
        })->filter()->values()->all();
    }

    private function loadTombstoneByBiblioId(int $biblioId): ?array
    {
        if ($biblioId <= 0) {
            return null;
        }
        $row = DB::table('audit_logs')
            ->where('action', 'delete')
            ->where('format', 'biblio')
            ->where('meta->biblio_id', $biblioId)
            ->orderByDesc('id')
            ->select(['meta', 'created_at'])
            ->first();
        if (!$row) {
            return null;
        }
        $meta = is_array($row->meta) ? $row->meta : (json_decode((string) ($row->meta ?? '{}'), true) ?: []);
        return [
            'id' => $biblioId,
            'institution_id' => (int) ($meta['institution_id'] ?? 0),
            'datestamp' => $this->toUtcDateTime((string) ($row->created_at ?? now())),
            'is_deleted' => true,
        ];
    }

    private function appendResumptionToken(\DOMDocument $doc, \DOMElement $parent, array $state, int $nextOffset, int $total): void
    {
        $token = $this->buildResumptionToken([
            'metadataPrefix' => (string) ($state['metadataPrefix'] ?? self::METADATA_PREFIX_DC),
            'snapshot_key' => (string) ($state['snapshot_key'] ?? ''),
            'offset' => $nextOffset,
        ]);
        $tokenNode = $doc->createElement('resumptionToken', $token);
        $tokenNode->setAttribute('cursor', (string) ($state['offset'] ?? 0));
        $tokenNode->setAttribute('completeListSize', (string) $total);
        $parent->appendChild($tokenNode);
    }

    private function institutionSetSpec(int $institutionId): ?string
    {
        if ($institutionId <= 0) {
            return null;
        }
        return 'institution:' . $institutionId;
    }

    private function parseInstitutionSetSpec(string $setSpec): int
    {
        if (!preg_match('/^institution:(\d+)$/', trim($setSpec), $m)) {
            return 0;
        }
        return (int) ($m[1] ?? 0);
    }

    private function detectGranularity(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1) {
            return 'day';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $v) === 1) {
            return 'datetime';
        }
        return '';
    }

    private function isValidGranularityToken(string $fromGranularity, string $untilGranularity): bool
    {
        $allowed = ['', 'day', 'datetime'];
        if (!in_array($fromGranularity, $allowed, true) || !in_array($untilGranularity, $allowed, true)) {
            return false;
        }
        if ($fromGranularity !== '' && $untilGranularity !== '' && $fromGranularity !== $untilGranularity) {
            return false;
        }
        return true;
    }

    private function parseOaiDate(string $value, bool $endOfDay): ?\Illuminate\Support\Carbon
    {
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                return $endOfDay
                    ? \Illuminate\Support\Carbon::parse($value)->endOfDay()
                    : \Illuminate\Support\Carbon::parse($value)->startOfDay();
            }
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseResumptionToken(string $token): ?array
    {
        try {
            $decoded = $this->base64UrlDecode($token);
            if ($decoded === '') {
                return null;
            }
            $wrapper = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($wrapper)) {
                return null;
            }
            $payloadB64 = (string) ($wrapper['p'] ?? '');
            $signature = (string) ($wrapper['s'] ?? '');
            if ($payloadB64 === '' || $signature === '') {
                return null;
            }
            $payloadJson = $this->base64UrlDecode($payloadB64);
            if ($payloadJson === '') {
                return null;
            }
            $expected = hash_hmac('sha256', $payloadJson, $this->tokenSigningKey());
            if (!hash_equals($expected, $signature)) {
                return null;
            }
            $data = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildResumptionToken(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $json, $this->tokenSigningKey());
        $wrapper = json_encode([
            'p' => $this->base64UrlEncode($json),
            's' => $sig,
        ], JSON_UNESCAPED_SLASHES);
        return $this->base64UrlEncode($wrapper);
    }

    private function oaiIdentifier(int $id): string
    {
        return 'oai:' . self::REPO_ID . ':biblio:' . $id;
    }

    private function parseOaiIdentifier(string $identifier): int
    {
        if (!str_starts_with($identifier, 'oai:' . self::REPO_ID . ':biblio:')) {
            return 0;
        }
        $parts = explode(':', $identifier);
        $id = (int) end($parts);
        return $id > 0 ? $id : 0;
    }

    private function createEnvelope(Request $request, string $verb, array $params): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS('http://www.openarchives.org/OAI/2.0/', 'OAI-PMH');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
        $doc->appendChild($root);

        $this->appendNode($doc, $root, 'responseDate', now()->utc()->format('Y-m-d\TH:i:s\Z'));
        $requestNode = $doc->createElement('request', route('oai.pmh', [], false));
        if ($verb !== '') {
            $requestNode->setAttribute('verb', $verb);
        }
        foreach ($params as $key => $value) {
            $str = trim((string) $value);
            if ($str === '') {
                continue;
            }
            $requestNode->setAttribute((string) $key, $str);
        }
        $root->appendChild($requestNode);

        return $doc;
    }

    private function errorResponse(string $code, string $message, Request $request, array $requestParams)
    {
        $verb = trim((string) $request->query('verb', ''));
        $doc = $this->createEnvelope($request, $verb, $requestParams);
        $root = $doc->documentElement;
        $err = $doc->createElement('error');
        $err->setAttribute('code', $code);
        $err->appendChild($doc->createTextNode($message));
        $root->appendChild($err);

        return $this->xmlResponse($doc);
    }

    private function toUtcDateTime(string $input): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($input)->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable $e) {
            return now()->utc()->format('Y-m-d\TH:i:s\Z');
        }
    }

    private function appendNode(\DOMDocument $doc, \DOMElement $parent, string $name, string $value): void
    {
        $node = $doc->createElement($name);
        $node->appendChild($doc->createTextNode($value));
        $parent->appendChild($node);
    }

    private function xmlResponse(\DOMDocument $doc)
    {
        return response($doc->saveXML(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
