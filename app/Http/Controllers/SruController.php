<?php

namespace App\Http\Controllers;

use App\Support\InteropMetrics;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SruController extends Controller
{
    private const MAX_RECORDS = 50;
    private const MAX_TERMS = 20;
    private const RECORD_SCHEMA_DC = 'info:srw/schema/1/dc-v1.1';
    private const RECORD_SCHEMA_OAI_MARC = 'info:srw/schema/1/oai_marc-v1.0';

    private const INDEX_MAP = [
        'any' => ['b.title', 'b.subtitle', 'b.publisher', 'b.isbn', 'b.issn', 'bm.dublin_core_json'],
        'cql.serverchoice' => ['b.title', 'b.subtitle', 'b.publisher', 'b.isbn', 'b.issn', 'bm.dublin_core_json'],
        'title' => ['b.title', 'b.subtitle'],
        'dc.title' => ['b.title', 'b.subtitle'],
        'publisher' => ['b.publisher'],
        'dc.publisher' => ['b.publisher'],
        'isbn' => ['b.isbn'],
        'issn' => ['b.issn'],
        'identifier' => ['b.isbn', 'b.issn', 'bm.dublin_core_json'],
        'dc.identifier' => ['b.isbn', 'b.issn', 'bm.dublin_core_json'],
        'creator' => ['bm.dublin_core_json'],
        'dc.creator' => ['bm.dublin_core_json'],
        'subject' => ['bm.dublin_core_json'],
        'dc.subject' => ['bm.dublin_core_json'],
    ];

    public function handle(Request $request)
    {
        $startedAt = microtime(true);
        try {
            $operation = trim((string) $request->query('operation', 'searchRetrieve'));

            return match ($operation) {
                'searchRetrieve' => $this->searchRetrieve($request),
                'scan' => $this->scan($request),
                'explain' => $this->explain($request),
                default => $this->diagnosticResponse('info:srw/diagnostic/1/7', 'Unsupported operation.', $operation),
            };
        } finally {
            InteropMetrics::recordLatency('sru', (microtime(true) - $startedAt) * 1000);
        }
    }

    private function explain(Request $request)
    {
        $doc = $this->createDoc();
        $root = $doc->documentElement;

        $root->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:version', '1.2'));

        $record = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:record');
        $root->appendChild($record);
        $record->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:recordSchema', 'info:srw/schema/1/explain-v1.0'));
        $record->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:recordPacking', 'xml'));

        $recordData = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:recordData');
        $record->appendChild($recordData);

        $explain = $doc->createElementNS('http://explain.z3950.org/dtd/2.1/', 'explain');
        $recordData->appendChild($explain);

        $serverInfo = $doc->createElement('serverInfo');
        $serverInfo->setAttribute('protocol', 'http');
        $serverInfo->setAttribute('version', '1.2');
        $serverInfo->setAttribute('method', 'GET');
        $serverInfo->setAttribute('transport', 'http');
        $serverInfo->setAttribute('host', (string) $request->getHost());
        $serverInfo->setAttribute('port', (string) ($request->getPort() ?: 80));
        $serverInfo->setAttribute('database', '/sru');
        $explain->appendChild($serverInfo);

        $databaseInfo = $doc->createElement('databaseInfo');
        $databaseInfo->appendChild($doc->createElement('title', config('app.name', 'NOTOBUKU') . ' SRU'));
        $databaseInfo->appendChild($doc->createElement('description', 'SearchRetrieve endpoint for bibliographic interoperability.'));
        $explain->appendChild($databaseInfo);

        $indexInfo = $doc->createElement('indexInfo');
        $explain->appendChild($indexInfo);

        $indexSet = $doc->createElement('set');
        $indexSet->setAttribute('name', 'dc');
        $indexSet->setAttribute('identifier', 'info:srw/cql-context-set/1/dc-v1.1');
        $indexInfo->appendChild($indexSet);

        foreach (['dc.title', 'dc.creator', 'dc.subject', 'dc.publisher', 'dc.identifier', 'cql.serverChoice'] as $name) {
            $index = $doc->createElement('index');
            $title = $doc->createElement('title', $name);
            $map = $doc->createElement('map');
            $map->appendChild($doc->createElement('name', $name));
            $index->appendChild($title);
            $index->appendChild($map);
            $indexInfo->appendChild($index);
        }

        $schemaInfo = $doc->createElement('schemaInfo');
        $explain->appendChild($schemaInfo);
        foreach ([self::RECORD_SCHEMA_DC, self::RECORD_SCHEMA_OAI_MARC] as $schema) {
            $schemaNode = $doc->createElement('schema');
            $schemaNode->setAttribute('name', $schema);
            $schemaNode->setAttribute('identifier', $schema);
            $schemaInfo->appendChild($schemaNode);
        }

        return $this->xmlResponse($doc);
    }

    private function searchRetrieve(Request $request)
    {
        $query = trim((string) $request->query('query', ''));
        if ($query === '') {
            return $this->diagnosticResponse('info:srw/diagnostic/1/7', 'Missing query parameter.', 'query');
        }

        $startRecord = max(1, (int) $request->query('startRecord', 1));
        $maximumRecords = (int) $request->query('maximumRecords', 10);
        if ($maximumRecords < 1) {
            $maximumRecords = 10;
        }
        if ($maximumRecords > self::MAX_RECORDS) {
            $maximumRecords = self::MAX_RECORDS;
        }
        $schema = $this->resolveRecordSchema((string) $request->query('recordSchema', self::RECORD_SCHEMA_DC));
        if ($schema === null) {
            return $this->diagnosticResponse('info:srw/diagnostic/1/66', 'Unsupported recordSchema.', (string) $request->query('recordSchema'));
        }
        $sortKeysRaw = trim((string) $request->query('sortKeys', ''));
        $sortKeys = $this->parseSortKeys($sortKeysRaw);

        $parsed = $this->parseCql($query);
        if (!$parsed['ok']) {
            return $this->diagnosticResponse($parsed['uri'], $parsed['message'], $parsed['details']);
        }

        $builder = DB::table('biblio as b')
            ->leftJoin('biblio_metadata as bm', 'bm.biblio_id', '=', 'b.id')
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
                'b.updated_at',
                'bm.dublin_core_json',
            ]);

        $this->applyParsedQuery($builder, $parsed['clauses']);

        $total = (clone $builder)->count('b.id');
        $offset = $startRecord - 1;
        $this->applySortKeys($builder, $sortKeys);
        if (count($sortKeys) === 0) {
            $builder->orderBy('b.id');
        } else {
            $builder->orderBy('b.id');
        }
        $rows = $builder->offset($offset)->limit($maximumRecords)->get();

        $doc = $this->createDoc();
        $root = $doc->documentElement;
        $root->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:version', '1.2'));
        $root->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:numberOfRecords', (string) $total));

        $recordsNode = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:records');
        $root->appendChild($recordsNode);

        foreach ($rows as $idx => $row) {
            $record = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:record');
            $recordsNode->appendChild($record);
            $record->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:recordSchema', $schema));
            $record->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:recordPacking', 'xml'));

            $recordData = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:recordData');
            $record->appendChild($recordData);
            if ($schema === self::RECORD_SCHEMA_OAI_MARC) {
                $recordData->appendChild($this->buildOaiMarcRecord($doc, $row));
            } else {
                $recordData->appendChild($this->buildDcRecord($doc, $row));
            }

            $position = $startRecord + $idx;
            $record->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:recordPosition', (string) $position));
        }

        $echo = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:echoedSearchRetrieveRequest');
        $echo->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:version', '1.2'));
        $echo->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:query', $query));
        $echo->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:startRecord', (string) $startRecord));
        $echo->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:maximumRecords', (string) $maximumRecords));
        $echo->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:recordSchema', $schema));
        if ($sortKeysRaw !== '') {
            $echo->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:sortKeys', $sortKeysRaw));
        }
        $root->appendChild($echo);

        return $this->xmlResponse($doc);
    }

    private function scan(Request $request)
    {
        $scanClause = trim((string) $request->query('scanClause', ''));
        if ($scanClause === '') {
            return $this->diagnosticResponse('info:srw/diagnostic/1/7', 'Missing scanClause parameter.', 'scanClause');
        }

        $maximumTerms = (int) $request->query('maximumTerms', 10);
        if ($maximumTerms < 1) {
            $maximumTerms = 10;
        }
        if ($maximumTerms > self::MAX_TERMS) {
            $maximumTerms = self::MAX_TERMS;
        }

        $parsed = $this->parseClause($scanClause);
        if (!$parsed['ok']) {
            return $this->diagnosticResponse($parsed['uri'], $parsed['message'], $parsed['details']);
        }

        $index = (string) ($parsed['index'] ?? 'cql.serverchoice');
        $term = (string) ($parsed['term'] ?? '');
        $columns = self::INDEX_MAP[$index] ?? self::INDEX_MAP['cql.serverchoice'];
        $scanColumn = $this->resolveScanColumn($columns);
        if ($scanColumn === null) {
            return $this->diagnosticResponse('info:srw/diagnostic/1/16', 'Unsupported index for scan.', $index);
        }

        $terms = DB::table('biblio as b')
            ->whereNotNull($scanColumn)
            ->where($scanColumn, '<>', '')
            ->where($scanColumn, 'like', $term . '%')
            ->selectRaw($scanColumn . ' as term, COUNT(*) as aggregate')
            ->groupBy($scanColumn)
            ->orderBy($scanColumn)
            ->limit($maximumTerms)
            ->get();

        $doc = $this->createDoc('scanResponse');
        $root = $doc->documentElement;
        $root->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:version', '1.2'));
        $termsNode = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:terms');
        $root->appendChild($termsNode);

        foreach ($terms as $item) {
            $termNode = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:term');
            $termsNode->appendChild($termNode);
            $termNode->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:value', (string) ($item->term ?? '')));
            $termNode->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:numberOfRecords', (string) ((int) ($item->aggregate ?? 0))));
        }

        return $this->xmlResponse($doc);
    }

    private function parseCql(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['ok' => false, 'uri' => 'info:srw/diagnostic/1/10', 'message' => 'Malformed query.', 'details' => $query];
        }

        $parts = preg_split('/\s+(and|or)\s+/i', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || count($parts) === 0) {
            return ['ok' => false, 'uri' => 'info:srw/diagnostic/1/10', 'message' => 'Malformed query.', 'details' => $query];
        }

        $clauses = [];
        $pendingBoolean = 'and';
        foreach ($parts as $i => $part) {
            $text = trim((string) $part);
            if ($text === '') {
                continue;
            }

            if ($i % 2 === 1) {
                $pendingBoolean = strtolower($text) === 'or' ? 'or' : 'and';
                continue;
            }

            $parsedClause = $this->parseClause($text);
            if (!$parsedClause['ok']) {
                return $parsedClause;
            }
            $parsedClause['boolean'] = count($clauses) === 0 ? 'and' : $pendingBoolean;
            $clauses[] = $parsedClause;
        }

        if (count($clauses) === 0) {
            return ['ok' => false, 'uri' => 'info:srw/diagnostic/1/10', 'message' => 'Malformed query.', 'details' => $query];
        }

        return ['ok' => true, 'clauses' => $clauses];
    }

    private function parseClause(string $text): array
    {
        if (preg_match('/^([a-z0-9_.]+)\s+([a-z=]+)\s+(.+)$/i', $text, $m) !== 1) {
            $term = trim($text, "\" \t\n\r\0\x0B");
            if ($term === '') {
                return ['ok' => false, 'uri' => 'info:srw/diagnostic/1/10', 'message' => 'Malformed clause.', 'details' => $text];
            }
            return [
                'ok' => true,
                'index' => 'cql.serverchoice',
                'relation' => 'any',
                'term' => $term,
            ];
        }

        $index = strtolower(trim((string) $m[1]));
        $relation = strtolower(trim((string) $m[2]));
        $term = trim((string) $m[3], "\" \t\n\r\0\x0B");
        if ($relation === '=') {
            $relation = 'exact';
        }
        if (!in_array($relation, ['any', 'all', 'exact'], true)) {
            return ['ok' => false, 'uri' => 'info:srw/diagnostic/1/19', 'message' => 'Unsupported relation.', 'details' => $relation];
        }
        if (!isset(self::INDEX_MAP[$index])) {
            return ['ok' => false, 'uri' => 'info:srw/diagnostic/1/16', 'message' => 'Unsupported index.', 'details' => $index];
        }
        if ($term === '') {
            return ['ok' => false, 'uri' => 'info:srw/diagnostic/1/10', 'message' => 'Malformed clause.', 'details' => $text];
        }

        return [
            'ok' => true,
            'index' => $index,
            'relation' => $relation,
            'term' => $term,
        ];
    }

    private function applyParsedQuery(Builder $builder, array $clauses): void
    {
        foreach ($clauses as $clause) {
            $boolean = $clause['boolean'] ?? 'and';
            $method = $boolean === 'or' ? 'orWhere' : 'where';
            $builder->{$method}(function (Builder $query) use ($clause) {
                $columns = self::INDEX_MAP[$clause['index']] ?? self::INDEX_MAP['cql.serverchoice'];
                $relation = $clause['relation'];
                $term = (string) $clause['term'];

                if ($relation === 'exact') {
                    $this->whereAnyColumnLike($query, $columns, $term);
                    return;
                }

                $tokens = preg_split('/\s+/', $term) ?: [];
                $tokens = array_values(array_filter(array_map('trim', $tokens), fn($v) => $v !== ''));
                if (count($tokens) === 0) {
                    $tokens = [$term];
                }

                if ($relation === 'all') {
                    foreach ($tokens as $token) {
                        $query->where(function (Builder $inner) use ($columns, $token) {
                            $this->whereAnyColumnLike($inner, $columns, $token);
                        });
                    }
                    return;
                }

                $query->where(function (Builder $inner) use ($columns, $tokens) {
                    foreach ($tokens as $idx => $token) {
                        if ($idx === 0) {
                            $this->whereAnyColumnLike($inner, $columns, $token);
                        } else {
                            $inner->orWhere(function (Builder $sub) use ($columns, $token) {
                                $this->whereAnyColumnLike($sub, $columns, $token);
                            });
                        }
                    }
                });
            });
        }
    }

    private function whereAnyColumnLike(Builder $query, array $columns, string $term): void
    {
        $like = '%' . $term . '%';
        foreach ($columns as $idx => $column) {
            if ($idx === 0) {
                $query->where($column, 'like', $like);
            } else {
                $query->orWhere($column, 'like', $like);
            }
        }
    }

    private function buildDcRecord(\DOMDocument $doc, object $row): \DOMElement
    {
        $dc = $doc->createElementNS('http://purl.org/dc/elements/1.1/', 'dc:dc');
        $dc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', 'http://purl.org/dc/elements/1.1/');

        $json = json_decode((string) ($row->dublin_core_json ?? ''), true);
        if (!is_array($json)) {
            $json = [];
        }

        $title = trim((string) ($row->title ?? ''));
        $subtitle = trim((string) ($row->subtitle ?? ''));
        if ($subtitle !== '') {
            $title .= ': ' . $subtitle;
        }
        $this->appendDcValues($doc, $dc, 'title', $json['title'] ?? [$title]);
        $this->appendDcValues($doc, $dc, 'creator', $json['creator'] ?? []);
        $this->appendDcValues($doc, $dc, 'subject', $json['subject'] ?? []);
        $this->appendDcValues($doc, $dc, 'publisher', $json['publisher'] ?? [(string) ($row->publisher ?? '')]);
        $this->appendDcValues($doc, $dc, 'date', $json['date'] ?? [(string) ($row->publish_year ?? '')]);
        $this->appendDcValues($doc, $dc, 'type', $json['type'] ?? [(string) ($row->material_type ?? 'text')]);
        $this->appendDcValues($doc, $dc, 'language', $json['language'] ?? [(string) ($row->language ?? 'id')]);

        $identifiers = $json['identifier'] ?? [];
        $identifiers = is_array($identifiers) ? $identifiers : [$identifiers];
        if (!empty($row->isbn)) {
            $identifiers[] = 'ISBN ' . (string) $row->isbn;
        }
        if (!empty($row->issn)) {
            $identifiers[] = 'ISSN ' . (string) $row->issn;
        }
        $identifiers[] = 'oai:notobuku:biblio:' . (int) $row->id;
        $this->appendDcValues($doc, $dc, 'identifier', $identifiers);

        return $dc;
    }

    private function buildOaiMarcRecord(\DOMDocument $doc, object $row): \DOMElement
    {
        $record = $doc->createElementNS('http://www.openarchives.org/OAI/1.1/oai_marc', 'oai_marc:record');
        $record->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:oai_marc', 'http://www.openarchives.org/OAI/1.1/oai_marc');
        $record->setAttribute('type', 'Bibliographic');
        $record->setAttribute('level', 'Monograph');

        $title = trim((string) ($row->title ?? ''));
        $subtitle = trim((string) ($row->subtitle ?? ''));
        if ($subtitle !== '') {
            $title .= ': ' . $subtitle;
        }

        $this->appendOaiMarcVarfield($doc, $record, '245', [['code' => 'a', 'value' => $title]]);
        $this->appendOaiMarcVarfield($doc, $record, '260', [
            ['code' => 'b', 'value' => (string) ($row->publisher ?? '')],
            ['code' => 'c', 'value' => (string) ($row->publish_year ?? '')],
        ]);
        $this->appendOaiMarcVarfield($doc, $record, '020', [['code' => 'a', 'value' => (string) ($row->isbn ?? '')]]);
        $this->appendOaiMarcVarfield($doc, $record, '022', [['code' => 'a', 'value' => (string) ($row->issn ?? '')]]);
        $this->appendOaiMarcVarfield($doc, $record, '041', [['code' => 'a', 'value' => (string) ($row->language ?? 'id')]]);

        return $record;
    }

    private function appendOaiMarcVarfield(\DOMDocument $doc, \DOMElement $record, string $tag, array $subfields): void
    {
        $valid = [];
        foreach ($subfields as $sf) {
            $code = trim((string) ($sf['code'] ?? ''));
            $value = trim((string) ($sf['value'] ?? ''));
            if ($code === '' || $value === '') {
                continue;
            }
            $valid[] = ['code' => $code, 'value' => $value];
        }
        if (count($valid) === 0) {
            return;
        }

        $varfield = $doc->createElementNS('http://www.openarchives.org/OAI/1.1/oai_marc', 'oai_marc:varfield');
        $varfield->setAttribute('id', $tag);
        foreach ($valid as $sf) {
            $sub = $doc->createElementNS('http://www.openarchives.org/OAI/1.1/oai_marc', 'oai_marc:subfield');
            $sub->setAttribute('label', (string) $sf['code']);
            $sub->appendChild($doc->createTextNode((string) $sf['value']));
            $varfield->appendChild($sub);
        }
        $record->appendChild($varfield);
    }

    private function appendDcValues(\DOMDocument $doc, \DOMElement $parent, string $name, mixed $values): void
    {
        $list = is_array($values) ? $values : [$values];
        foreach ($list as $value) {
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $node = $doc->createElementNS('http://purl.org/dc/elements/1.1/', 'dc:' . $name);
            $node->appendChild($doc->createTextNode($text));
            $parent->appendChild($node);
        }
    }

    private function parseSortKeys(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = array_values(array_filter(array_map('trim', explode(' ', preg_replace('/\s+/', ' ', $raw)))));
        $keys = [];
        foreach ($parts as $part) {
            $segments = explode(',', $part);
            $field = strtolower(trim((string) ($segments[0] ?? '')));
            if ($field === '') {
                continue;
            }
            $direction = 'asc';
            $last = trim((string) end($segments));
            if ($last === '0') {
                $direction = 'desc';
            } elseif ($last === '1') {
                $direction = 'asc';
            } elseif (str_contains($part, ':')) {
                [$f, $dir] = array_map('trim', explode(':', $part, 2));
                $field = strtolower($f);
                $direction = strtolower($dir) === 'desc' ? 'desc' : 'asc';
            }

            $column = $this->resolveSortColumn($field);
            if ($column === null) {
                continue;
            }
            $keys[] = ['column' => $column, 'direction' => $direction];
        }
        return $keys;
    }

    private function resolveSortColumn(string $field): ?string
    {
        return match ($field) {
            'title', 'dc.title' => 'b.title',
            'date', 'dc.date', 'year', 'publish_year' => 'b.publish_year',
            'publisher', 'dc.publisher' => 'b.publisher',
            default => null,
        };
    }

    private function applySortKeys(Builder $builder, array $sortKeys): void
    {
        foreach ($sortKeys as $sort) {
            $column = (string) ($sort['column'] ?? '');
            $direction = strtolower((string) ($sort['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            if ($column === '') {
                continue;
            }
            $builder->orderBy($column, $direction);
        }
    }

    private function diagnosticResponse(string $uri, string $message, string $details = '')
    {
        $doc = $this->createDoc();
        $root = $doc->documentElement;
        $root->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:version', '1.2'));
        $diagnostics = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:diagnostics');
        $root->appendChild($diagnostics);

        $diag = $doc->createElementNS('http://www.loc.gov/zing/srw/diagnostic/', 'diag:diagnostic');
        $diag->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:diag', 'http://www.loc.gov/zing/srw/diagnostic/');
        $diagnostics->appendChild($diag);
        $diag->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/diagnostic/', 'diag:uri', $uri));
        $diag->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/diagnostic/', 'diag:message', $message));
        if ($details !== '') {
            $diag->appendChild($doc->createElementNS('http://www.loc.gov/zing/srw/diagnostic/', 'diag:details', $details));
        }

        return $this->xmlResponse($doc);
    }

    private function resolveRecordSchema(string $schema): ?string
    {
        $value = strtolower(trim($schema));
        if ($value === '' || $value === 'dc' || $value === strtolower(self::RECORD_SCHEMA_DC)) {
            return self::RECORD_SCHEMA_DC;
        }
        if ($value === 'oai_marc' || $value === strtolower(self::RECORD_SCHEMA_OAI_MARC)) {
            return self::RECORD_SCHEMA_OAI_MARC;
        }
        return null;
    }

    private function resolveScanColumn(array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($column === 'bm.dublin_core_json') {
                continue;
            }
            if (str_starts_with($column, 'b.')) {
                return $column;
            }
        }
        return 'b.title';
    }

    private function createDoc(string $rootElement = 'searchRetrieveResponse'): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS('http://www.loc.gov/zing/srw/', 'srw:' . $rootElement);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:srw', 'http://www.loc.gov/zing/srw/');
        $doc->appendChild($root);
        return $doc;
    }

    private function xmlResponse(\DOMDocument $doc)
    {
        return response($doc->saveXML(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
