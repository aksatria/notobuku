<?php

namespace App\Services;

use App\Models\AuthorityAuthor;
use App\Models\AuthorityPublisher;
use App\Models\AuthoritySubject;
use App\Models\Biblio;
use App\Models\BiblioIdentifier;
use App\Models\BiblioMetadata;
use App\Models\DdcClass;
use App\Models\MarcSetting;
use App\Services\MarcValidationService;
use App\Services\MarcControlFieldBuilder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class MetadataMappingService
{
    public function normalize(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }

    public function toDublinCore(Biblio $biblio): array
    {
        $biblio->loadMissing(['authors', 'subjects', 'identifiers']);

        $title = $this->cleanTitleProper((string) $biblio->title);
        $subtitle = trim((string) ($biblio->subtitle ?? ''));
        $fullTitle = $subtitle !== '' ? $title . ': ' . $subtitle : $title;

        $creators = $biblio->authors?->pluck('name')->filter()->values()->all() ?? [];
        $subjects = $biblio->subjects?->pluck('term')->filter()->values()->all() ?? [];

        $description = trim((string) ($biblio->general_note ?? $biblio->notes ?? $biblio->ai_summary ?? ''));
        $description = $description !== '' ? $description : null;

        $identifierList = $biblio->identifiers?->map(function ($id) {
            return $id->scheme . ':' . $id->value;
        })->filter()->values()->all() ?? [];

        if (empty($identifierList)) {
            $fallback = trim((string) ($biblio->isbn ?? $biblio->issn ?? $biblio->call_number ?? ''));
            if ($fallback !== '') {
                $identifierList = [$fallback];
            }
        }

        return [
            'title' => $fullTitle,
            'creator' => $creators,
            'subject' => $subjects,
            'description' => $description,
            'publisher' => $biblio->publisher,
            'date' => $biblio->publish_year ? (string) $biblio->publish_year : null,
            'language' => $biblio->language,
            'identifier' => $identifierList,
            'type' => $biblio->material_type ?? 'buku',
            'format' => $biblio->media_type ?? 'teks',
        ];
    }

    public function toMarcCore(Biblio $biblio): array
    {
        $biblio->loadMissing(['authors', 'subjects', 'identifiers']);

        $title = $this->cleanTitleProper((string) $biblio->title);
        $subtitle = trim((string) ($biblio->subtitle ?? ''));

        $authors = $biblio->authors?->map(function ($a) {
            return [
                'name' => $a->name ?? null,
                'role' => $a->pivot?->role ?? $a->role ?? null,
                'ind1' => $a->pivot?->ind1 ?? $a->ind1 ?? null,
            ];
        })->filter(fn($row) => !empty($row['name']))->values()->all() ?? [];
        $subjects = $biblio->subjects?->map(function ($s) {
            $term = $s->term ?? $s->name ?? null;
            $scheme = $s->scheme ?? 'local';
            $type = $s->pivot?->type ?? $s->type ?? null;
            return [
                'term' => $term,
                'scheme' => $scheme,
                'type' => $type,
            ];
        })->filter(fn($row) => !empty($row['term']))->values()->all() ?? [];

        $identifiers = $biblio->identifiers?->map(function ($id) {
            return [
                'scheme' => strtolower(trim((string) $id->scheme)),
                'value' => trim((string) $id->value),
                'uri' => trim((string) ($id->uri ?? '')),
            ];
        })->filter(fn($row) => $row['scheme'] !== '' && $row['value'] !== '')->values()->all() ?? [];

        $fields = [];
        $haystack = $this->buildMediaHaystack($biblio);
        $isAudiobook = $this->isAudiobookHaystack($haystack);
        $isOnline = $this->isOnlineHaystack($haystack, $identifiers, $biblio->institution_id ?? null);
        $isVideo = str_contains($haystack, 'video') || str_contains($haystack, 'dvd') || str_contains($haystack, 'film');
        $isMusic = str_contains($haystack, 'music') || str_contains($haystack, 'musik');
        $isAudio = $isAudiobook || str_contains($haystack, 'audio') || str_contains($haystack, 'sound');
        $isOnlineAudio = $isOnline && ($isAudio || $isMusic);

        if (!empty($biblio->isbn)) {
            $fields['020'][] = ['a' => $biblio->isbn];
        }

        if (!empty($biblio->issn)) {
            $fields['022'][] = ['a' => $biblio->issn];
        }

        if (!empty($biblio->language)) {
            $langs = $this->normalizeLanguages($biblio->language);
            foreach ($langs as $lang) {
                $fields['041'][] = ['a' => $lang];
            }
        }

        $hasCorporate = false;
        $hasMeeting = false;
        $publisherAsMain = false;

        $mainRel = null;
        if (!empty($authors)) {
            $main = $authors[0];
            $rel = $this->mapRelator($main['role'] ?? null);
            $mainRel = $rel;
            if ($this->isMeetingAuthor($main)) {
                $hasMeeting = true;
                $f111 = ['a' => $main['name']];
                $authId = $this->findAuthorityAuthorId($main['name']);
                $authSub = $this->buildAuthoritySubfields('author', $authId);
                foreach ($authSub as $k => $v) {
                    $f111[$k] = $v;
                }
                if (isset($main['ind1']) && in_array((string) $main['ind1'], ['0', '1', '2', ' '], true)) {
                    $f111['_ind1'] = (string) $main['ind1'];
                }
                if (!empty($rel['term'])) {
                    $f111['e'] = $rel['term'];
                }
                if (!empty($rel['code'])) {
                    $f111['4'] = $rel['code'];
                }
                $fields['111'][] = $f111;
            } elseif ($this->isCorporateAuthor($main)) {
                $hasCorporate = true;
                $f110 = ['a' => $main['name']];
                $authId = $this->findAuthorityAuthorId($main['name']);
                $authSub = $this->buildAuthoritySubfields('author', $authId);
                foreach ($authSub as $k => $v) {
                    $f110[$k] = $v;
                }
                if (!empty($rel['term'])) {
                    $f110['e'] = $rel['term'];
                }
                if (!empty($rel['code'])) {
                    $f110['4'] = $rel['code'];
                }
                $fields['110'][] = $f110;
            } else {
                $f100 = [
                    '_ind1' => $this->computePersonalNameIndicator($main['name']),
                    'a' => $main['name'],
                ];
                $authId = $this->findAuthorityAuthorId($main['name']);
                $authSub = $this->buildAuthoritySubfields('author', $authId);
                foreach ($authSub as $k => $v) {
                    $f100[$k] = $v;
                }
                if (!empty($rel['term'])) {
                    $f100['e'] = $rel['term'];
                }
                if (!empty($rel['code'])) {
                    $f100['4'] = $rel['code'];
                }
                $fields['100'][] = $f100;
            }
        }

        if ($title !== '') {
            $hasMainEntry = !empty($authors);
            $f245 = [
                '_ind1' => $hasMainEntry ? '1' : '0',
                '_ind2' => (string) $this->computeNonFilingIndicator($title),
                'a' => $title,
            ];
            if ($subtitle !== '') {
                $f245['b'] = $subtitle;
            }
            if (!empty($biblio->responsibility_statement)) {
                $f245['c'] = $biblio->responsibility_statement;
            } elseif (!empty($authors) && $mainRel && ($mainRel['code'] ?? null) === 'nrt') {
                $f245['c'] = 'Narrated by ' . $authors[0]['name'];
            }
            $fields['245'][] = $f245;
        }

        if (!empty($biblio->edition)) {
            $fields['250'][] = ['a' => $biblio->edition];
        }
        if (!empty($biblio->frequency)) {
            $fields['310'][] = ['a' => $biblio->frequency];
        }
        if (!empty($biblio->former_frequency)) {
            $fields['321'][] = ['a' => $biblio->former_frequency];
        }
        if (!empty($biblio->serial_beginning) || !empty($biblio->serial_ending)) {
            $summary = trim((string) $biblio->serial_beginning);
            $ending = trim((string) $biblio->serial_ending);
            if ($ending !== '') {
                $summary = $summary !== '' ? ($summary . ' - ' . $ending) : $ending;
            }
            if ($summary !== '') {
                $fields['362'][] = ['_ind1' => '0', 'a' => $summary];
            }
        }
        if (!empty($biblio->serial_first_issue) || !empty($biblio->serial_last_issue)) {
            $field363 = ['_ind1' => '0'];
            if (!empty($biblio->serial_first_issue)) {
                $field363['a'] = $biblio->serial_first_issue;
            }
            if (!empty($biblio->serial_last_issue)) {
                $field363['b'] = $biblio->serial_last_issue;
            }
            $fields['363'][] = $field363;
        }
        if (!empty($biblio->serial_source_note)) {
            $fields['588'][] = ['a' => $biblio->serial_source_note];
        }
        if (!empty($biblio->serial_preceding_title)) {
            $field780 = ['_ind1' => '0', 't' => $biblio->serial_preceding_title];
            if (!empty($biblio->serial_preceding_issn)) {
                $field780['x'] = $biblio->serial_preceding_issn;
            }
            $fields['780'][] = $field780;
        }
        if (!empty($biblio->serial_succeeding_title)) {
            $field785 = ['_ind1' => '0', 't' => $biblio->serial_succeeding_title];
            if (!empty($biblio->serial_succeeding_issn)) {
                $field785['x'] = $biblio->serial_succeeding_issn;
            }
            $fields['785'][] = $field785;
        }

        $pubField = [];
        if (!empty($biblio->place_of_publication)) {
            $pubField['a'] = $biblio->place_of_publication;
        }
        if (!empty($biblio->publisher)) {
            $pubField['b'] = $biblio->publisher;
        }
        if (!empty($biblio->publish_year)) {
            $pubField['c'] = (string) $biblio->publish_year;
        }
        if (!empty($pubField)) {
            $pubField['_ind2'] = '1';
            $fields['264'][] = $pubField;
        }

        if (!empty($subjects)) {
            foreach ($subjects as $subject) {
                $scheme = strtolower(trim((string) ($subject['scheme'] ?? 'local')));
                $term = $subject['term'];
                $type = strtolower(trim((string) ($subject['type'] ?? 'topic')));

                $tag = match ($type) {
                    'person', 'personal', 'person_name' => '600',
                    'corporate', 'organization', 'organisasi', 'corporate_name' => '610',
                    'meeting', 'conference', 'meeting_name' => '611',
                    'uniform', 'uniform_title', 'title' => '630',
                    'geographic', 'place', 'geographic_name' => '651',
                    default => '650',
                };

                $field = ['a' => $term];
                if ($scheme === 'lcsh') {
                    $field['_ind2'] = '0';
                } else {
                    $field['_ind2'] = '7';
                    $field['2'] = $scheme !== '' ? $scheme : 'local';
                }
                $authId = $this->findAuthoritySubjectId($term, $scheme !== '' ? $scheme : 'local');
                $authSub = $this->buildAuthoritySubfields('subject', $authId);
                foreach ($authSub as $k => $v) {
                    $field[$k] = $v;
                }
                $fields[$tag][] = $field;
            }
        }
        if ($isAudiobook) {
            $fields['655'][] = ['_ind2' => '7', 'a' => 'Audiobooks', '2' => 'local'];
        }

        if (!empty($biblio->series_title)) {
            $fields['490'][] = ['_ind1' => '1', 'a' => $biblio->series_title];
            $fields['830'][] = ['a' => $biblio->series_title];
        }

        $rda = $this->buildRda3xx($biblio);
        if (!empty($rda)) {
            foreach ($rda as $tag => $entries) {
                foreach ($entries as $entry) {
                    $fields[$tag][] = $entry;
                }
            }
        }

        $physical = $this->buildPhysicalDesc($biblio, $isOnlineAudio);
        if (!empty($physical)) {
            $fields['300'][] = $physical;
        }
        $fields34x = $this->buildRda34x($isAudio, $isMusic, $isVideo, $isOnline);
        if (!empty($fields34x)) {
            foreach ($fields34x as $tag => $rows) {
                foreach ($rows as $row) {
                    $fields[$tag][] = $row;
                }
            }
        }
        if ($isOnline) {
            $fields['538'][] = ['a' => 'Mode of access: World Wide Web.'];
        }

        $note = trim((string) ($biblio->notes ?? ''));
        $generalNote = trim((string) ($biblio->general_note ?? ''));
        if ($note !== '') {
            $fields['500'][] = ['a' => $note];
        }
        if ($generalNote !== '' && $generalNote !== $note) {
            $fields['500'][] = ['a' => $generalNote];
        }
        $localNote = trim((string) ($biblio->local_note ?? ''));
        if ($localNote !== '') {
            $fields['590'][] = ['a' => $localNote];
        }
        $bibNote = trim((string) ($biblio->bibliography_note ?? ''));
        if ($bibNote !== '') {
            $fields['504'][] = ['a' => $bibNote];
        }
        $summary = trim((string) ($biblio->ai_summary ?? ''));
        if ($summary !== '') {
            $fields['520'][] = ['a' => $summary];
        }
        $contents = trim((string) ($biblio->contents_note ?? ''));
        if ($contents !== '') {
            $fields['505'][] = ['a' => $contents];
        }
        if (!empty($biblio->citation_note)) {
            $fields['510'][] = ['a' => $biblio->citation_note];
        }
        if (!empty($biblio->audience) || !empty($biblio->audience_note)) {
            $audience = $biblio->audience_note ?? $biblio->audience;
            if (!empty($audience)) {
                $fields['521'][] = ['a' => $audience];
            }
        }
        if (!empty($biblio->language_note)) {
            $fields['546'][] = ['a' => $biblio->language_note];
        }

        if (!empty($biblio->ddc)) {
            $ddcNormalized = $this->normalizeDdc((string) $biblio->ddc);
            $ddcValue = $ddcNormalized ?? trim((string) $biblio->ddc);
            $edition = $this->getDdcEdition();
            $field082 = ['_ind1' => '0', '_ind2' => '4', 'a' => $ddcValue];
            if ($edition !== '') {
                $field082['2'] = $edition;
            }
            $fields['082'][] = $field082;
        }
        if (!empty($biblio->call_number)) {
            $fields['090'][] = ['a' => $biblio->call_number];
        }

        if (count($authors) > 1) {
            foreach (array_slice($authors, 1) as $row) {
                $rel = $this->mapRelator($row['role'] ?? null);
                if ($this->isMeetingAuthor($row)) {
                    $hasMeeting = true;
                    $f711 = ['a' => $row['name']];
                    $authId = $this->findAuthorityAuthorId($row['name']);
                    $authSub = $this->buildAuthoritySubfields('author', $authId);
                    foreach ($authSub as $k => $v) {
                        $f711[$k] = $v;
                    }
                    if (isset($row['ind1']) && in_array((string) $row['ind1'], ['0', '1', '2', ' '], true)) {
                        $f711['_ind1'] = (string) $row['ind1'];
                    }
                    if (!empty($rel['term'])) {
                        $f711['e'] = $rel['term'];
                    }
                    if (!empty($rel['code'])) {
                        $f711['4'] = $rel['code'];
                    }
                    $fields['711'][] = $f711;
                } elseif ($this->isCorporateAuthor($row)) {
                    $hasCorporate = true;
                    $f710 = ['a' => $row['name']];
                    $authId = $this->findAuthorityAuthorId($row['name']);
                    $authSub = $this->buildAuthoritySubfields('author', $authId);
                    foreach ($authSub as $k => $v) {
                        $f710[$k] = $v;
                    }
                    if (!empty($rel['term'])) {
                        $f710['e'] = $rel['term'];
                    }
                    if (!empty($rel['code'])) {
                        $f710['4'] = $rel['code'];
                    }
                    $fields['710'][] = $f710;
                } else {
                    $f700 = [
                        '_ind1' => $this->computePersonalNameIndicator($row['name']),
                        'a' => $row['name'],
                    ];
                    $authId = $this->findAuthorityAuthorId($row['name']);
                    $authSub = $this->buildAuthoritySubfields('author', $authId);
                    foreach ($authSub as $k => $v) {
                        $f700[$k] = $v;
                    }
                    if (!empty($rel['term'])) {
                        $f700['e'] = $rel['term'];
                    }
                    if (!empty($rel['code'])) {
                        $f700['4'] = $rel['code'];
                    }
                    $fields['700'][] = $f700;
                }
            }
        }

        $publisherNormalized = null;
        if (!$hasCorporate && !empty($biblio->publisher)) {
            $publisherNormalized = $this->normalizeEntityName($biblio->publisher);
            foreach ($authors as $row) {
                $authorName = $row['name'] ?? '';
                if ($authorName !== '' && $this->normalizeEntityName($authorName) === $publisherNormalized) {
                    $publisherNormalized = '';
                    break;
                }
            }
        }

        if (!$hasCorporate && !empty($biblio->publisher)) {
            if ($publisherNormalized === '') {
                // avoid duplicate corporate entry when publisher already in authors
            } else {
            $rel = $this->mapRelator('publisher');
            $publisherField = ['a' => $biblio->publisher];
            $authId = $this->findAuthorityPublisherId($biblio->publisher);
            $authSub = $this->buildAuthoritySubfields('publisher', $authId);
            foreach ($authSub as $k => $v) {
                $publisherField[$k] = $v;
            }
            if (!empty($rel['term'])) {
                $publisherField['e'] = $rel['term'];
            }
            if (!empty($rel['code'])) {
                $publisherField['4'] = $rel['code'];
            }
            if (empty($authors)) {
                $publisherAsMain = true;
                $fields['110'][] = $publisherField;
            } else {
                $fields['710'][] = $publisherField;
            }
            }
        }

        if ($title !== '') {
            $hasMainEntry = !empty($authors) || $publisherAsMain;
            if (!empty($fields['245'])) {
                $fields['245'][0]['_ind1'] = $hasMainEntry ? '1' : '0';
            }
        }

        if (!empty($identifiers)) {
            $seen856 = [];
            foreach ($identifiers as $id) {
                $scheme = $id['scheme'];
                $value = $id['value'];
                $uri = $id['uri'];

                if (in_array($scheme, ['uri', 'url'], true)) {
                    $target = $value !== '' ? $value : $uri;
                    if ($target !== '') {
                        $key = strtolower(trim((string) $target));
                        if (!isset($seen856[$key])) {
                            $fields['856'][] = ['_ind1' => '4', '_ind2' => '0', 'u' => $target, 'y' => 'Akses Online'];
                            $seen856[$key] = true;
                        }
                    }
                    continue;
                }

                if ($uri !== '') {
                    $key = strtolower(trim((string) $uri));
                    if (!isset($seen856[$key])) {
                        $fields['856'][] = ['_ind1' => '4', '_ind2' => '0', 'u' => $uri, 'y' => 'Akses Online'];
                        $seen856[$key] = true;
                    }
                }

                if (in_array($scheme, ['doi', 'isni', 'orcid', 'oclc', 'lccn', 'handle'], true)) {
                    $fields['024'][] = ['_ind1' => '7', 'a' => $value, '2' => $scheme];
                }
            }
        }

        if (!empty($biblio->variant_title)) {
            $fields['246'][] = ['a' => $biblio->variant_title];
        }
        if (!empty($biblio->former_title)) {
            $fields['247'][] = ['a' => $biblio->former_title];
        }

        $holdings = $this->buildHoldingsFields($biblio);
        if (!empty($holdings)) {
            foreach ($holdings as $tag => $rows) {
                foreach ($rows as $row) {
                    $fields[$tag][] = $row;
                }
            }
        }

        return $fields;
    }

    private function buildHoldingsFields(Biblio $biblio): array
    {
        if (!(bool) config('marc.include_holdings_fields', true)) {
            return [];
        }

        $biblio->loadMissing(['items', 'items.branch']);
        $items = $biblio->items ?? collect();

        $institutionCode = $this->resolveInstitutionCode((int) ($biblio->institution_id ?? 0));
        $out = [];
        $summary = trim((string) ($biblio->holdings_summary ?? ''));
        $supplement = trim((string) ($biblio->holdings_supplement ?? ''));
        $index = trim((string) ($biblio->holdings_index ?? ''));
        if ($summary !== '') {
            $out['866'][] = ['_ind1' => ' ', '_ind2' => ' ', 'a' => $summary];
        }
        if ($supplement !== '') {
            $out['867'][] = ['_ind1' => ' ', '_ind2' => ' ', 'a' => $supplement];
        }
        if ($index !== '') {
            $out['868'][] = ['_ind1' => ' ', '_ind2' => ' ', 'a' => $index];
        }

        if ($items->isEmpty()) {
            return $out;
        }
        foreach ($items as $item) {
            $branchName = $item->branch?->name ?? null;
            $location = $item->location_note ?? null;
            $callNumber = $biblio->call_number ?? null;
            $barcode = $item->barcode ?? null;
            $status = $item->status ?? $item->circulation_status ?? null;

            $field852 = ['_ind1' => ' ', '_ind2' => ' '];
            if ($institutionCode) $field852['a'] = $institutionCode;
            if (!empty($branchName)) $field852['b'] = $branchName;
            if (!empty($location)) $field852['c'] = $location;
            if (!empty($callNumber)) $field852['h'] = $callNumber;
            if (!empty($barcode)) $field852['p'] = $barcode;
            $out['852'][] = $field852;

            $field876 = ['_ind1' => ' ', '_ind2' => ' '];
            if (!empty($barcode)) $field876['p'] = $barcode;
            if (!empty($item->accession_number)) $field876['t'] = $item->accession_number;
            if (!empty($item->inventory_number)) $field876['a'] = $item->inventory_number;
            if (!empty($status)) $field876['j'] = $status;
            if (!empty($item->condition)) $field876['x'] = $item->condition;
            if (!empty($item->notes)) $field876['z'] = $item->notes;
            $out['876'][] = $field876;

            if (!empty($item->acquisition_source)) {
                $out['877'][] = ['_ind1' => ' ', '_ind2' => ' ', 'a' => $item->acquisition_source];
            }
            if (!empty($item->source)) {
                $out['878'][] = ['_ind1' => ' ', '_ind2' => ' ', 'a' => $item->source];
            }
        }

        return $out;
    }

    public function ensureAuthorityAuthor(string $name): AuthorityAuthor
    {
        $preferred = trim($name);
        $normalized = $this->normalize($preferred);

        $author = AuthorityAuthor::query()->firstOrCreate(
            ['normalized_name' => $normalized],
            ['preferred_name' => $preferred]
        );

        $this->maybeAppendAlias($author, $preferred);

        return $author;
    }

    public function ensureAuthoritySubject(string $term, string $scheme = 'local'): AuthoritySubject
    {
        $preferred = trim($term);
        $normalized = $this->normalize($preferred);

        $subject = AuthoritySubject::query()->firstOrCreate(
            ['scheme' => $scheme, 'normalized_term' => $normalized],
            ['preferred_term' => $preferred, 'scheme' => $scheme]
        );

        $this->maybeAppendAlias($subject, $preferred);

        return $subject;
    }

    public function ensureAuthorityPublisher(string $name): AuthorityPublisher
    {
        $preferred = trim($name);
        $normalized = $this->normalize($preferred);

        $publisher = AuthorityPublisher::query()->firstOrCreate(
            ['normalized_name' => $normalized],
            ['preferred_name' => $preferred]
        );

        $this->maybeAppendAlias($publisher, $preferred);

        return $publisher;
    }

    public function syncMetadataForBiblio(
        Biblio $biblio,
        ?array $dcI18n = null,
        ?array $identifiers = null
    ): BiblioMetadata
    {
        $biblio->loadMissing(['authors', 'subjects']);

        foreach ($biblio->authors as $author) {
            if (!empty($author->name)) {
                $this->ensureAuthorityAuthor($author->name);
            }
        }

        foreach ($biblio->subjects as $subject) {
            $term = $subject->term ?? $subject->name;
            if (!empty($term)) {
                $scheme = $subject->scheme ?? 'local';
                $this->ensureAuthoritySubject($term, $scheme);
            }
        }

        if (!empty($biblio->publisher)) {
            $this->ensureAuthorityPublisher($biblio->publisher);
        }

        $dublin = $this->toDublinCore($biblio);
        $marc = $this->toMarcCore($biblio);
        $globalIdentifiers = $this->syncGlobalIdentifiers($biblio, $identifiers ?? []);

        $dcI18n = $this->sanitizeI18n($dcI18n);

        $metadata = BiblioMetadata::query()->updateOrCreate(
            ['biblio_id' => $biblio->id],
            [
                'dublin_core_json' => $dublin,
                'dublin_core_i18n_json' => $dcI18n,
                'marc_core_json' => $marc,
                'global_identifiers_json' => $globalIdentifiers,
            ]
        );

        $validator = new MarcValidationService();
        $issues = $validator->validateForExport($biblio);
        if (!empty($issues)) {
            Log::warning('MARC validation warnings on save.', [
                'biblio_id' => $biblio->id,
                'issues' => $issues,
            ]);
        }

        if (!empty($biblio->ddc)) {
            $normalized = $this->normalizeDdc((string) $biblio->ddc);
            $base = $this->extractDdcBase($normalized ?? (string) $biblio->ddc);
            $ddc = $base !== '' ? DdcClass::query()->where('code', $base)->first() : null;
            if ($ddc) {
                $biblio->ddcClasses()->syncWithoutDetaching([$ddc->id]);
            }
        }

        return $metadata;
    }

    public function syncGlobalIdentifiers(Biblio $biblio, array $extra = []): array
    {
        $list = [];

        $this->pushIdentifier($list, 'isbn', $biblio->isbn);
        $this->pushIdentifier($list, 'issn', $biblio->issn);
        $this->pushIdentifier($list, 'call_number', $biblio->call_number);

        foreach ($extra as $row) {
            if (!is_array($row)) {
                continue;
            }
            $scheme = trim((string) ($row['scheme'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $uri = trim((string) ($row['uri'] ?? ''));
            if ($scheme === '' || $value === '') {
                continue;
            }
            $list[] = [
                'scheme' => $scheme,
                'value' => $value,
                'uri' => $uri !== '' ? $uri : null,
            ];
        }

        $unique = [];
        foreach ($list as $row) {
            $scheme = strtolower(trim((string) $row['scheme']));
            $value = trim((string) $row['value']);
            if ($scheme === '' || $value === '') {
                continue;
            }
            $normalized = $this->normalizeIdentifierValue($scheme, $value);
            $key = $scheme . '::' . $normalized;
            if (!isset($unique[$key])) {
                $unique[$key] = [
                    'scheme' => $scheme,
                    'value' => $value,
                    'normalized_value' => $normalized,
                    'uri' => $row['uri'] ?? null,
                ];
            }
        }

        foreach ($unique as $row) {
            BiblioIdentifier::query()->updateOrCreate(
                [
                    'biblio_id' => $biblio->id,
                    'scheme' => $row['scheme'],
                    'normalized_value' => $row['normalized_value'],
                ],
                [
                    'value' => $row['value'],
                    'uri' => $row['uri'],
                ]
            );
        }

        $biblio->loadMissing('identifiers');

        return $biblio->identifiers->map(fn($id) => [
            'scheme' => $id->scheme,
            'value' => $id->value,
            'uri' => $id->uri,
        ])->values()->all();
    }

    private function pushIdentifier(array &$list, string $scheme, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '') return;

        $list[] = [
            'scheme' => $scheme,
            'value' => $value,
            'uri' => null,
        ];
    }

    private function normalizeIdentifierValue(string $scheme, string $value): string
    {
        $scheme = strtolower(trim($scheme));
        $value = trim($value);

        if (in_array($scheme, ['isbn', 'issn'], true)) {
            return preg_replace('/[^0-9Xx]/', '', $value);
        }

        return (string) Str::of($value)->lower()->squish();
    }

    private function buildPhysicalDesc(Biblio $biblio, bool $isOnlineAudio = false): array
    {
        $field = [];

        $physical = trim((string) ($biblio->physical_desc ?? ''));
        $extent = trim((string) ($biblio->extent ?? ''));
        $illustrations = trim((string) ($biblio->illustrations ?? ''));
        $dimensions = trim((string) ($biblio->dimensions ?? ''));

        if ($physical !== '') {
            $field['a'] = $physical;
        } elseif ($extent !== '') {
            $field['a'] = $extent;
        } elseif ($isOnlineAudio) {
            $field['a'] = '1 online audio file';
        }

        if ($illustrations !== '') {
            $field['b'] = $illustrations;
        }

        if ($dimensions !== '') {
            $field['c'] = $dimensions;
        }

        return $field;
    }

    private function normalizeDdc(string $value): ?string
    {
        $v = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($v === '') return null;

        if (!preg_match('/^(\d{3})(?:\.(\d+))?(?:\s+([A-Za-z0-9][A-Za-z0-9\.\-]*))?$/', $v, $m)) {
            return null;
        }

        $base = $m[1] . (isset($m[2]) ? '.' . $m[2] : '');
        $cutter = isset($m[3]) ? strtoupper($m[3]) : null;

        return $cutter ? $base . ' ' . $cutter : $base;
    }

    private function extractDdcBase(string $value): string
    {
        $v = trim((string) $value);
        if ($v === '') return '';
        if (preg_match('/^(\d{3})(?:\.(\d+))?/', $v, $m)) {
            return $m[1] . (isset($m[2]) ? '.' . $m[2] : '');
        }
        return '';
    }

    private function getDdcEdition(): string
    {
        $row = MarcSetting::query()->where('key', 'ddc_edition')->first();
        $val = $row?->value_json;
        $edition = null;
        if (is_string($val)) {
            $edition = $val;
        } elseif (is_array($val)) {
            $edition = is_string($val['value'] ?? null) ? $val['value'] : null;
        }

        if ($edition === null || trim($edition) === '') {
            $edition = (string) config('marc.ddc_edition', '23');
        }

        return trim($edition);
    }

    private function computePersonalNameIndicator(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '' || !str_contains($name, ',')) return '0';

        $parts = explode(',', $name, 2);
        $after = trim($parts[1] ?? '');
        if ($after === '') return '0';

        if (preg_match('/^\\d{4}(?:-\\d{4})?-?$/', $after)) {
            return '1';
        }

        $suffixes = [
            'dr', 'drs', 'ir',
            'phd', 'md', 'dds', 'jd',
            'mba', 'ma', 'msc', 'meng', 'mt', 'mkom',
            'ssi', 'skm', 'st', 'se', 'sh',
            'sp', 'spog',
            'prof', 'mr', 'mrs', 'ms',
            'jr', 'sr', 'ii', 'iii', 'iv',
            'h', 'hj', 'haji', 'hajjah',
            'ust', 'ustad', 'ustadz', 'ustaz', 'ustadzah', 'ustazah',
            'kyai', 'kiai', 'kh', 'nyai',
            'r', 'ra', 'rh', 'raden', 'ratu',
            'tuan', 'puan',
        ];
        $suffixSet = array_fill_keys($suffixes, true);

        $tokens = preg_split('/[\\s,]+/', $after);
        $tokens = array_values(array_filter(array_map(function ($t) {
            $t = strtolower(trim((string) $t));
            $t = preg_replace('/[^a-z0-9]/', '', $t);
            return $t;
        }, $tokens), fn($t) => $t !== ''));

        if (empty($tokens)) return '0';

        foreach ($tokens as $token) {
            if (!isset($suffixSet[$token])) {
                return '1';
            }
        }

        return '0';
    }

    private function buildMediaHaystack(Biblio $biblio): string
    {
        $material = strtolower(trim((string) $biblio->material_type));
        $media = strtolower(trim((string) $biblio->media_type));
        $haystack = trim($material . ' ' . $media);

        $isSoundtrack = str_contains($haystack, 'soundtrack') || str_contains($haystack, 'ost');
        $isAudiobook = $this->isAudiobookHaystack($haystack);

        if ($isSoundtrack && !str_contains($haystack, 'music')) {
            $haystack .= ' music';
        }
        if ($isAudiobook && !str_contains($haystack, 'audio')) {
            $haystack .= ' audio';
        }

        return preg_replace('/\\s+/', ' ', trim($haystack));
    }

    private function isAudiobookHaystack(string $haystack): bool
    {
        return str_contains($haystack, 'audiobook') || str_contains($haystack, 'audio book');
    }

    private function isOnlineHaystack(string $haystack, array $identifiers, ?int $institutionId = null): bool
    {
        $mode = $this->getOnlineDetectionMode($institutionId);

        $hasUriScheme = function () use ($identifiers): bool {
            foreach ($identifiers as $id) {
                $scheme = strtolower(trim((string) ($id['scheme'] ?? '')));
                $value = trim((string) ($id['value'] ?? ''));
                $uri = trim((string) ($id['uri'] ?? ''));
                if (in_array($scheme, ['uri', 'url'], true) && ($value !== '' || $uri !== '')) {
                    return true;
                }
            }
            return false;
        };

        if ($mode === 'strict') {
            return $hasUriScheme();
        }

        if (str_contains($haystack, 'ebook') || str_contains($haystack, 'online') || str_contains($haystack, 'computer') || str_contains($haystack, 'digital')) {
            return true;
        }

        if ($hasUriScheme()) {
            return true;
        }

        foreach ($identifiers as $id) {
            $uri = trim((string) ($id['uri'] ?? ''));
            if ($uri !== '') {
                return true;
            }
        }

        return false;
    }

    private function getOnlineDetectionMode(?int $institutionId = null): string
    {
        $fallback = (string) config('marc.online_detection_mode', 'strict');
        try {
            $row = MarcSetting::query()->where('key', 'online_detection_mode')->first();
            $val = $row?->value_json;
            if (is_string($val) && $val !== '') {
                return $val;
            }
            if (is_array($val)) {
                $institutions = $val['institutions'] ?? null;
                if ($institutionId && is_array($institutions)) {
                    $specific = $institutions[(string) $institutionId] ?? null;
                    if (is_string($specific) && $specific !== '') {
                        return $specific;
                    }
                }
                $v = $val['value'] ?? null;
                if (is_string($v) && $v !== '') {
                    return $v;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $fallback;
    }

    private function normalizeLanguages(string $value): array
    {
        $parts = preg_split('/[;,\/]+/', $value);
        $parts = array_map(fn($v) => strtolower(trim((string) $v)), $parts ?: []);
        $parts = array_values(array_filter($parts, fn($v) => $v !== ''));
        if (empty($parts)) return [];

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

        $normalized = [];
        foreach ($parts as $part) {
            if (isset($map[$part])) {
                $normalized[] = $map[$part];
                continue;
            }
            if (strlen($part) === 3) {
                $normalized[] = $part;
                continue;
            }
            if (strlen($part) > 3) {
                $normalized[] = substr($part, 0, 3);
                continue;
            }
            $normalized[] = 'und';
        }

        return array_values(array_unique($normalized));
    }

    private function mapRelator(?string $value): array
    {
        $v = strtolower(trim((string) $value));
        if ($v === '') return ['term' => null, 'code' => null];

        $map = [
            'pengarang' => ['term' => 'author', 'code' => 'aut'],
            'author' => ['term' => 'author', 'code' => 'aut'],
            'penulis' => ['term' => 'author', 'code' => 'aut'],
            'editor' => ['term' => 'editor', 'code' => 'edt'],
            'penyunting' => ['term' => 'editor', 'code' => 'edt'],
            'ilustrator' => ['term' => 'illustrator', 'code' => 'ill'],
            'illustrator' => ['term' => 'illustrator', 'code' => 'ill'],
            'penerjemah' => ['term' => 'translator', 'code' => 'trl'],
            'translator' => ['term' => 'translator', 'code' => 'trl'],
            'fotografer' => ['term' => 'photographer', 'code' => 'pht'],
            'photographer' => ['term' => 'photographer', 'code' => 'pht'],
            'komposer' => ['term' => 'composer', 'code' => 'cmp'],
            'composer' => ['term' => 'composer', 'code' => 'cmp'],
            'sutradara' => ['term' => 'director', 'code' => 'drt'],
            'director' => ['term' => 'director', 'code' => 'drt'],
            'narator' => ['term' => 'narrator', 'code' => 'nrt'],
            'narrator' => ['term' => 'narrator', 'code' => 'nrt'],
            'kontributor' => ['term' => 'contributor', 'code' => 'ctb'],
            'contributor' => ['term' => 'contributor', 'code' => 'ctb'],
            'penyusun' => ['term' => 'compiler', 'code' => 'com'],
            'compiler' => ['term' => 'compiler', 'code' => 'com'],
            'penerbit' => ['term' => 'publisher', 'code' => 'pbl'],
            'publisher' => ['term' => 'publisher', 'code' => 'pbl'],
            'penyadur' => ['term' => 'adapter', 'code' => 'adp'],
            'adapter' => ['term' => 'adapter', 'code' => 'adp'],
            'pencipta' => ['term' => 'creator', 'code' => 'cre'],
            'creator' => ['term' => 'creator', 'code' => 'cre'],
            'penyaji' => ['term' => 'performer', 'code' => 'prf'],
            'performer' => ['term' => 'performer', 'code' => 'prf'],
            'aktor' => ['term' => 'actor', 'code' => 'act'],
            'actor' => ['term' => 'actor', 'code' => 'act'],
            'produser' => ['term' => 'producer', 'code' => 'pro'],
            'producer' => ['term' => 'producer', 'code' => 'pro'],
            'penata musik' => ['term' => 'arranger', 'code' => 'arr'],
            'arranger' => ['term' => 'arranger', 'code' => 'arr'],
            'desainer' => ['term' => 'designer', 'code' => 'dsr'],
            'designer' => ['term' => 'designer', 'code' => 'dsr'],
            'programmer' => ['term' => 'programmer', 'code' => 'prg'],
            'pemrogram' => ['term' => 'programmer', 'code' => 'prg'],
            'ilustrator sampul' => ['term' => 'cover designer', 'code' => 'cov'],
            'cover designer' => ['term' => 'cover designer', 'code' => 'cov'],
            'pengulas' => ['term' => 'reviewer', 'code' => 'rev'],
            'reviewer' => ['term' => 'reviewer', 'code' => 'rev'],
            'penata letak' => ['term' => 'designer', 'code' => 'dsr'],
            'penata grafis' => ['term' => 'designer', 'code' => 'dsr'],
            'penyunting bahasa' => ['term' => 'editor', 'code' => 'edt'],
            'editor bahasa' => ['term' => 'editor', 'code' => 'edt'],
            'peneliti' => ['term' => 'researcher', 'code' => 'res'],
            'researcher' => ['term' => 'researcher', 'code' => 'res'],
            'meeting' => ['term' => 'meeting', 'code' => 'mtg'],
            'conference' => ['term' => 'meeting', 'code' => 'mtg'],
            'seminar' => ['term' => 'meeting', 'code' => 'mtg'],
            'symposium' => ['term' => 'meeting', 'code' => 'mtg'],
            'simposium' => ['term' => 'meeting', 'code' => 'mtg'],
            'kongres' => ['term' => 'meeting', 'code' => 'mtg'],
            'konferensi' => ['term' => 'meeting', 'code' => 'mtg'],
        ];

        if (isset($map[$v])) {
            return $map[$v];
        }

        $reverse = [];
        foreach ($map as $row) {
            if (!empty($row['code']) && !empty($row['term'])) {
                $reverse[$row['code']] = $row['term'];
            }
        }

        if (strlen($v) === 3) {
            return [
                'term' => $reverse[$v] ?? null,
                'code' => $v,
            ];
        }

        return ['term' => $v, 'code' => null];
    }

    private function isCorporateAuthor(array $row): bool
    {
        $role = strtolower(trim((string) ($row['role'] ?? '')));
        if (in_array($role, ['corporate', 'organization', 'organisasi', 'lembaga', 'instansi'], true)) {
            return true;
        }

        $name = strtolower(trim((string) ($row['name'] ?? '')));
        if ($name === '') return false;

        $keywords = [
            'universitas', 'university', 'college', 'institute', 'institut',
            'kementerian', 'ministry', 'department', 'dept', 'agency',
            'company', 'co.', 'inc', 'corp', 'ltd', 'pt ', 'cv ',
            'yayasan', 'foundation', 'association', 'asosiasi',
            'bank', 'hospital', 'rs ', 'rumah sakit',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($name, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function isMeetingAuthor(array $row): bool
    {
        $role = strtolower(trim((string) ($row['role'] ?? '')));
        if (in_array($role, ['meeting', 'conference', 'seminar', 'symposium', 'simposium', 'kongres', 'konferensi', 'workshop'], true)) {
            return true;
        }

        $name = strtolower(trim((string) ($row['name'] ?? '')));
        if ($name === '') return false;

        $keywords = [
            'conference', 'konferensi', 'seminar', 'symposium', 'simposium',
            'workshop', 'kongres', 'congress', 'colloquium', 'panel', 'meeting',
            'rapat', 'sidang', 'musyawarah',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($name, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function computeNonFilingIndicator(string $title): int
    {
        $t = $this->stripLeadingPunctuation((string) $title);
        $lower = function_exists('mb_strtolower')
            ? mb_strtolower($t, 'UTF-8')
            : strtolower($t);
        $articles = [
            'a ',
            'an ',
            'the ',
            'si ',
            'sang ',
            'se ',
            'sebuah ',
            'seorang ',
            'para ',
            'la ',
            'le ',
            'les ',
            'el ',
            'los ',
            'las ',
            'l\'',
            'l’',
            'der ',
            'die ',
            'das ',
            'il ',
            'lo ',
            'un ',
            'una ',
        ];

        usort($articles, function ($a, $b) {
            $lenA = function_exists('mb_strlen') ? mb_strlen($a, 'UTF-8') : strlen($a);
            $lenB = function_exists('mb_strlen') ? mb_strlen($b, 'UTF-8') : strlen($b);
            return $lenB <=> $lenA;
        });

        foreach ($articles as $article) {
            if (str_starts_with($lower, $article)) {
                return function_exists('mb_strlen')
                    ? mb_strlen($article, 'UTF-8')
                    : strlen($article);
            }
        }

        return 0;
    }

    private function stripLeadingPunctuation(string $value): string
    {
        $value = ltrim($value);
        $pattern = '/^([\\s\\"\\\'\\“\\”\\‘\\’\\(\\)\\[\\]\\{\\}\\<\\>]+)+/u';
        while ($value !== '' && preg_match($pattern, $value)) {
            $value = preg_replace($pattern, '', $value, 1);
        }
        return ltrim($value);
    }

    private function buildRda3xx(Biblio $biblio): array
    {
        $material = strtolower(trim((string) $biblio->material_type));
        $media = strtolower(trim((string) $biblio->media_type));
        $haystack = trim($material . ' ' . $media);
        $builder = new MarcControlFieldBuilder();
        $profile = $builder->getMediaProfile($biblio);

        $content = ['text', 'txt', 'rdacontent'];
        $mediaType = ['unmediated', 'n', 'rdamedia'];
        $carrier = ['volume', 'nc', 'rdacarrier'];

        $isOnline = str_contains($haystack, 'ebook')
            || str_contains($haystack, 'online')
            || str_contains($haystack, 'computer')
            || str_contains($haystack, 'digital');
        $isVideo = str_contains($haystack, 'video')
            || str_contains($haystack, 'dvd')
            || str_contains($haystack, 'film');
        $isSoundtrack = str_contains($haystack, 'soundtrack') || str_contains($haystack, 'ost');
        $isAudiobook = str_contains($haystack, 'audiobook') || str_contains($haystack, 'audio book');
        $profileName = strtolower(trim((string) ($profile['name'] ?? '')));
        $type006 = strtolower(trim((string) ($profile['type_006'] ?? '')));
        $type007 = strtolower(trim((string) ($profile['type_007'] ?? '')));
        $isMusicProfile = $type006 === 'j'
            || str_contains($profileName, 'music')
            || str_contains($profileName, 'musik');
        $isAudioProfile = $type006 === 'i'
            || (str_starts_with($type007, 'sd') && !$isMusicProfile)
            || (str_contains($profileName, 'audio') && !$isMusicProfile);
        $isMusic = $isSoundtrack || $isMusicProfile || str_contains($haystack, 'music') || str_contains($haystack, 'musik');
        $isAudio = $isAudiobook || $isAudioProfile || str_contains($haystack, 'audio') || str_contains($haystack, 'sound');
        $isMap = str_contains($haystack, 'map') || str_contains($haystack, 'atlas') || str_contains($haystack, 'kartografi');

        $profileRda = $this->resolveRdaFromProfile($profile, $isOnline);
        if (!empty($profileRda)) {
            return $profileRda;
        }

        if ($isVideo) {
            $content = ['two-dimensional moving image', 'tdi', 'rdacontent'];
            if ($isOnline) {
                $mediaType = ['computer', 'c', 'rdamedia'];
                $carrier = ['online resource', 'cr', 'rdacarrier'];
            } else {
                $mediaType = ['video', 'v', 'rdamedia'];
                $carrier = ['videodisc', 'vd', 'rdacarrier'];
            }
        } elseif ($isMusic) {
            $content = ['performed music', 'prm', 'rdacontent'];
            if ($isOnline) {
                $mediaType = ['computer', 'c', 'rdamedia'];
                $carrier = ['online resource', 'cr', 'rdacarrier'];
            } else {
                $mediaType = ['audio', 's', 'rdamedia'];
                $carrier = ['audio disc', 'sd', 'rdacarrier'];
            }
        } elseif ($isAudio) {
            $content = ['spoken word', 'spw', 'rdacontent'];
            if ($isOnline) {
                $mediaType = ['computer', 'c', 'rdamedia'];
                $carrier = ['online resource', 'cr', 'rdacarrier'];
            } else {
                $mediaType = ['audio', 's', 'rdamedia'];
                $carrier = ['audio disc', 'sd', 'rdacarrier'];
            }
        } elseif ($isMap) {
            $content = ['cartographic image', 'cri', 'rdacontent'];
            if ($isOnline) {
                $mediaType = ['computer', 'c', 'rdamedia'];
                $carrier = ['online resource', 'cr', 'rdacarrier'];
            } else {
                $mediaType = ['unmediated', 'n', 'rdamedia'];
                $carrier = ['sheet', 'nb', 'rdacarrier'];
            }
        } elseif ($isOnline) {
            $content = ['text', 'txt', 'rdacontent'];
            $mediaType = ['computer', 'c', 'rdamedia'];
            $carrier = ['online resource', 'cr', 'rdacarrier'];
        }

        return [
            '336' => [[
                'a' => $content[0],
                'b' => $content[1],
                '2' => $content[2],
            ]],
            '337' => [[
                'a' => $mediaType[0],
                'b' => $mediaType[1],
                '2' => $mediaType[2],
            ]],
            '338' => [[
                'a' => $carrier[0],
                'b' => $carrier[1],
                '2' => $carrier[2],
            ]],
        ];
    }

    private function resolveRdaFromProfile(array $profile, bool $isOnline): array
    {
        $rda336 = $profile['rda_336'] ?? null;
        $rda337 = $profile['rda_337'] ?? null;
        $rda338 = $profile['rda_338'] ?? null;

        if ($isOnline) {
            $rda336 = $profile['rda_336_online'] ?? $rda336;
            $rda337 = $profile['rda_337_online'] ?? $rda337;
            $rda338 = $profile['rda_338_online'] ?? $rda338;
        }

        if (!is_array($rda336) || !is_array($rda337) || !is_array($rda338)) {
            return [];
        }

        foreach (['a', 'b', '2'] as $key) {
            if (empty($rda336[$key]) || empty($rda337[$key]) || empty($rda338[$key])) {
                return [];
            }
        }

        return [
            '336' => [[
                'a' => $rda336['a'],
                'b' => $rda336['b'],
                '2' => $rda336['2'],
            ]],
            '337' => [[
                'a' => $rda337['a'],
                'b' => $rda337['b'],
                '2' => $rda337['2'],
            ]],
            '338' => [[
                'a' => $rda338['a'],
                'b' => $rda338['b'],
                '2' => $rda338['2'],
            ]],
        ];
    }

    private function sanitizeI18n(?array $i18n): ?array
    {
        if (empty($i18n) || !is_array($i18n)) {
            return null;
        }

        $allowedKeys = ['title', 'creator', 'subject', 'description', 'publisher', 'date', 'language', 'identifier', 'type', 'format'];
        $clean = [];

        foreach ($i18n as $locale => $payload) {
            $locale = trim((string) $locale);
            if ($locale === '' || !is_array($payload)) {
                continue;
            }
            $row = [];
            foreach ($payload as $k => $v) {
                if (!in_array($k, $allowedKeys, true)) continue;
                if (is_array($v)) {
                    $v = array_values(array_filter(array_map('strval', $v)));
                } else {
                    $v = trim((string) $v);
                }
                $row[$k] = $v;
            }
            if (!empty($row)) {
                $clean[$locale] = $row;
            }
        }

        return !empty($clean) ? $clean : null;
    }

    private function maybeAppendAlias($model, string $value): void
    {
        $value = trim($value);
        if ($value === '') return;

        $preferred = $model->preferred_name ?? ($model->preferred_term ?? null);

        $current = $model->aliases ?? [];
        if (!is_array($current)) {
            $current = [];
        }

        if (!in_array($value, $current, true) && $preferred !== $value) {
            $current[] = $value;
            $model->aliases = array_values(array_unique($current));
            $model->save();
        }
    }

    private function normalizeEntityName(string $value): string
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') return '';

        $tokens = explode(' ', $normalized);
        $stopwords = [
            'pt', 'cv', 'inc', 'ltd', 'corp', 'co', 'company', 'limited', 'gmbh', 'sarl',
            'tbk', 'tbh', 'persero', 'perseroan', 'se', 'sa', 'nv', 'plc', 'bv',
        ];
        $filtered = array_values(array_filter($tokens, fn($t) => $t !== '' && !in_array($t, $stopwords, true)));

        return !empty($filtered) ? implode(' ', $filtered) : $normalized;
    }

    private function buildRda34x(bool $isAudio, bool $isMusic, bool $isVideo, bool $isOnline): array
    {
        if (!$isOnline) {
            return [];
        }

        $category = 'text';
        if ($isVideo) {
            $category = 'video';
        } elseif ($isAudio || $isMusic) {
            $category = 'audio';
        }

        $map = (array) config('marc.rda_34x.' . $category, []);
        if (empty($map)) {
            return [];
        }

        $out = [];
        foreach ($map as $tag => $rows) {
            if (!is_array($rows)) continue;
            foreach ($rows as $row) {
                if (is_array($row) && !empty($row)) {
                    $out[$tag][] = $row;
                }
            }
        }

        return $out;
    }

    private function findAuthorityAuthorId(string $name): ?int
    {
        $normalized = $this->normalize($name);
        if ($normalized === '') return null;

        return AuthorityAuthor::query()
            ->where('normalized_name', $normalized)
            ->value('id');
    }

    private function findAuthoritySubjectId(string $term, string $scheme): ?int
    {
        $normalized = $this->normalize($term);
        if ($normalized === '') return null;
        $scheme = trim(strtolower($scheme));
        if ($scheme === '') $scheme = 'local';

        return AuthoritySubject::query()
            ->where('scheme', $scheme)
            ->where('normalized_term', $normalized)
            ->value('id');
    }

    private function findAuthorityPublisherId(string $name): ?int
    {
        $normalized = $this->normalize($name);
        if ($normalized === '') return null;

        return AuthorityPublisher::query()
            ->where('normalized_name', $normalized)
            ->value('id');
    }

    private function buildAuthorityControlNumber(string $type, int $id): string
    {
        $type = preg_replace('/[^a-z0-9_]+/i', '', strtolower($type));
        return 'NBK:' . $type . ':' . $id;
    }

    private function buildAuthoritySubfields(string $type, ?int $id): array
    {
        if ($id === null) {
            return [];
        }

        $out = ['0' => $this->buildAuthorityControlNumber($type, $id)];
        $uri = $this->resolveAuthorityUri($type, $id);
        if ($uri !== null && $uri !== '') {
            $out['1'] = $uri;
        }

        return $out;
    }

    private function resolveAuthorityUri(string $type, int $id): ?string
    {
        $external = $this->getAuthorityExternalIds($type, $id);
        if (empty($external)) {
            return null;
        }

        $priority = (array) config('marc.authority_source_priority', ['lcnaf', 'viaf', 'isni', 'wikidata', 'uri']);
        $map = (array) config('marc.authority_uri_map', []);

        foreach ($priority as $source) {
            $key = strtolower(trim((string) $source));
            if ($key === '') continue;
            $value = $external[$key] ?? null;
            if (!is_string($value) || trim($value) === '') continue;
            $value = trim($value);
            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }
            $tpl = $map[$key] ?? null;
            if (is_string($tpl) && $tpl !== '') {
                return str_replace('{id}', $value, $tpl);
            }
        }

        return null;
    }

    private function getAuthorityExternalIds(string $type, int $id): array
    {
        $type = strtolower(trim($type));
        $row = null;
        if ($type === 'author') {
            $row = AuthorityAuthor::query()->find($id);
        } elseif ($type === 'subject') {
            $row = AuthoritySubject::query()->find($id);
        } elseif ($type === 'publisher') {
            $row = AuthorityPublisher::query()->find($id);
        }
        $external = $row?->external_ids;
        return is_array($external) ? array_change_key_case($external, CASE_LOWER) : [];
    }

    private function cleanTitleProper(string $title): string
    {
        $clean = preg_replace('/^[\\p{P}\\p{S}\\s]+|[\\p{P}\\p{S}\\s]+$/u', '', (string) $title);
        $clean = trim(preg_replace('/\\s+/', ' ', (string) $clean));
        return $clean === '' ? trim($title) : $clean;
    }

    private function resolveInstitutionCode(int $institutionId): ?string
    {
        if ($institutionId <= 0) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\DB::table('institutions')
                ->where('id', $institutionId)
                ->value('code');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
