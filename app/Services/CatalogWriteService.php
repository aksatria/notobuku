<?php

namespace App\Services;

use App\Http\Requests\StoreBiblioRequest;
use App\Http\Requests\UpdateBiblioRequest;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Biblio;
use App\Models\Item;
use App\Models\Subject;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogWriteService
{
    private function currentInstitutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    private function normalizeLoose(string $text): string
    {
        return Str::of($text)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->toString();
    }

    private function normalizeTitle(string $title, ?string $subtitle = null): string
    {
        $base = trim($title);
        $subtitle = trim((string) $subtitle);
        if ($subtitle !== '') {
            $base .= ' ' . $subtitle;
        }

        return $this->normalizeLoose($base);
    }

    private function parseIdentifiersInput($identifiers): array
    {
        if (!is_array($identifiers)) {
            return [];
        }

        $clean = [];
        foreach ($identifiers as $row) {
            if (!is_array($row)) {
                continue;
            }

            $scheme = trim((string) ($row['scheme'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $uri = trim((string) ($row['uri'] ?? ''));
            if ($scheme === '' || $value === '') {
                continue;
            }

            $clean[] = [
                'scheme' => $scheme,
                'value' => $value,
                'uri' => $uri !== '' ? $uri : null,
            ];
        }

        return $clean;
    }

    private function normalizeDcI18nInput($dcI18n): array
    {
        if (!is_array($dcI18n)) {
            return [];
        }

        $normalizeList = function ($value): array {
            if (is_array($value)) {
                return array_values(array_filter(array_map('trim', array_map('strval', $value))));
            }

            $value = trim((string) $value);
            if ($value === '') {
                return [];
            }

            $parts = preg_split('/[;,\n]+/', $value);
            return array_values(array_filter(array_map('trim', $parts)));
        };

        $clean = [];
        foreach ($dcI18n as $locale => $payload) {
            $locale = trim((string) $locale);
            if ($locale === '' || !is_array($payload)) {
                continue;
            }

            $row = $payload;
            if (array_key_exists('creator', $row)) {
                $row['creator'] = $normalizeList($row['creator']);
            }
            if (array_key_exists('subject', $row)) {
                $row['subject'] = $normalizeList($row['subject']);
            }

            $clean[$locale] = $row;
        }

        return $clean;
    }

    private function generateUniqueCode(string $prefix, string $column): string
    {
        $date = now()->format('Ymd');
        for ($tries = 0; $tries < 20; $tries++) {
            $code = $prefix . '-' . $date . '-' . Str::upper(Str::random(6));
            $exists = Item::query()->where($column, $code)->exists();
            if (!$exists) {
                return $code;
            }
        }

        return $prefix . '-' . $date . '-' . Str::upper(Str::random(10));
    }

    public function store(
        StoreBiblioRequest $request,
        MetadataMappingService $metadataService,
        AiCatalogingService $aiCatalogingService
    ): RedirectResponse {
        $data = $request->validated();

        $institutionId = $this->currentInstitutionId();
        $gate = ['ok' => true, 'errors' => [], 'warnings' => []];
        if ((bool) config('notobuku.catalog.quality_gate.enabled', true)) {
            /** @var \App\Services\CatalogQualityGateService $qualityGate */
            $qualityGate = app(\App\Services\CatalogQualityGateService::class);
            $gate = $qualityGate->evaluate($data, $institutionId, null);
            if (!$gate['ok']) {
                return back()
                    ->withInput()
                    ->withErrors(['quality_gate' => implode(' ', (array) ($gate['errors'] ?? []))]);
            }
        }

        $title = trim($data['title']);
        $subtitle = isset($data['subtitle']) ? trim((string) $data['subtitle']) : null;
        $subtitle = ($subtitle !== '' ? $subtitle : null);

        $coverPath = null;
        $coverError = null;
        try {
            $file = $request->file('cover');
            \Log::info('Cover upload (store) debug', [
                'has_file' => $request->hasFile('cover'),
                'file_keys' => array_keys($request->allFiles() ?? []),
                'name' => $file?->getClientOriginalName(),
                'size' => $file?->getSize(),
                'error' => $file?->getError(),
                'mime' => $file?->getClientMimeType(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Cover upload (store) debug failed: ' . $e->getMessage());
        }
        if ($request->hasFile('cover')) {
            try {
                $coverPath = $request->file('cover')->store('covers', 'public');
            } catch (\Throwable $e) {
                $coverPath = null;
                $coverError = $e->getMessage();
            }
        }

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => $title,
            'subtitle' => $subtitle,
            'normalized_title' => $this->normalizeTitle($title, $subtitle),
            'responsibility_statement' => isset($data['responsibility_statement'])
                ? (trim((string) $data['responsibility_statement']) ?: null)
                : null,
            'publisher' => isset($data['publisher']) ? trim((string) $data['publisher']) ?: null : null,
            'place_of_publication' => isset($data['place_of_publication']) ? trim((string) $data['place_of_publication']) ?: null : null,
            'publish_year' => $data['publish_year'] ?? null,
            'isbn' => isset($data['isbn']) ? trim((string) $data['isbn']) ?: null : null,
            'issn' => isset($data['issn']) ? trim((string) $data['issn']) ?: null : null,
            'language' => isset($data['language']) ? trim((string) $data['language']) ?: 'id' : 'id',
            'edition' => isset($data['edition']) ? trim((string) $data['edition']) ?: null : null,
            'physical_desc' => isset($data['physical_desc']) ? trim((string) $data['physical_desc']) ?: null : null,
            'extent' => isset($data['extent']) ? trim((string) $data['extent']) ?: null : null,
            'dimensions' => isset($data['dimensions']) ? trim((string) $data['dimensions']) ?: null : null,
            'illustrations' => isset($data['illustrations']) ? trim((string) $data['illustrations']) ?: null : null,
            'series_title' => isset($data['series_title']) ? trim((string) $data['series_title']) ?: null : null,
            'cover_path' => $coverPath,
            'ddc' => isset($data['ddc']) ? trim((string) $data['ddc']) ?: null : null,
            'call_number' => isset($data['call_number']) ? trim((string) $data['call_number']) ?: null : null,
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) ?: null : null,
            'bibliography_note' => isset($data['bibliography_note']) ? trim((string) $data['bibliography_note']) ?: null : null,
            'general_note' => isset($data['general_note']) ? trim((string) $data['general_note']) ?: null : null,
            'frequency' => isset($data['frequency']) ? trim((string) $data['frequency']) ?: null : null,
            'former_frequency' => isset($data['former_frequency']) ? trim((string) $data['former_frequency']) ?: null : null,
            'serial_beginning' => isset($data['serial_beginning']) ? trim((string) $data['serial_beginning']) ?: null : null,
            'serial_ending' => isset($data['serial_ending']) ? trim((string) $data['serial_ending']) ?: null : null,
            'serial_first_issue' => isset($data['serial_first_issue']) ? trim((string) $data['serial_first_issue']) ?: null : null,
            'serial_last_issue' => isset($data['serial_last_issue']) ? trim((string) $data['serial_last_issue']) ?: null : null,
            'serial_source_note' => isset($data['serial_source_note']) ? trim((string) $data['serial_source_note']) ?: null : null,
            'serial_preceding_title' => isset($data['serial_preceding_title']) ? trim((string) $data['serial_preceding_title']) ?: null : null,
            'serial_preceding_issn' => isset($data['serial_preceding_issn']) ? trim((string) $data['serial_preceding_issn']) ?: null : null,
            'serial_succeeding_title' => isset($data['serial_succeeding_title']) ? trim((string) $data['serial_succeeding_title']) ?: null : null,
            'serial_succeeding_issn' => isset($data['serial_succeeding_issn']) ? trim((string) $data['serial_succeeding_issn']) ?: null : null,
            'holdings_summary' => isset($data['holdings_summary']) ? trim((string) $data['holdings_summary']) ?: null : null,
            'holdings_supplement' => isset($data['holdings_supplement']) ? trim((string) $data['holdings_supplement']) ?: null : null,
            'holdings_index' => isset($data['holdings_index']) ? trim((string) $data['holdings_index']) ?: null : null,
            'material_type' => isset($data['material_type']) ? (trim((string) $data['material_type']) ?: 'buku') : 'buku',
            'media_type' => isset($data['media_type']) ? (trim((string) $data['media_type']) ?: 'teks') : 'teks',
            'audience' => isset($data['audience']) ? trim((string) $data['audience']) ?: null : null,
            'is_reference' => isset($data['is_reference'])
                ? (in_array((string) $data['is_reference'], ['1', 'true', 'on', 'yes'], true))
                : false,
            'ai_status' => 'draft',
        ]);

        $useRoles = (string)($data['authors_role_mode'] ?? '0') === '1';
        $authorsRoles = $request->input('authors_roles_json');
        if ($useRoles && is_array($authorsRoles) && !empty($authorsRoles)) {
            $syncAuthors = [];
            $rows = collect($authorsRoles)
                ->filter(fn ($row) => is_array($row))
                ->map(function ($row) {
                    return [
                        'name' => trim((string)($row['name'] ?? '')),
                        'role' => trim((string)($row['role'] ?? 'aut')),
                    ];
                })
                ->filter(fn ($row) => $row['name'] !== '')
                ->values();

            foreach ($rows as $i => $row) {
                $name = $row['name'];
                $role = $row['role'] !== '' ? $row['role'] : 'aut';
                if ($role === 'pengarang') {
                    $role = 'aut';
                }

                $normalized = $this->normalizeLoose($name);
                $author = Author::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );
                $syncAuthors[$author->id] = ['role' => $role, 'sort_order' => $i + 1];
            }

            if (!empty($syncAuthors)) {
                $biblio->authors()->sync($syncAuthors);
            }
        } else {
            $authors = collect(explode(',', (string) $data['authors_text']))
                ->map(fn ($x) => trim($x))
                ->filter()
                ->values();

            foreach ($authors as $i => $name) {
                $normalized = $this->normalizeLoose($name);
                $author = Author::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );
                $biblio->authors()->syncWithoutDetaching([
                    $author->id => ['role' => 'pengarang', 'sort_order' => $i + 1],
                ]);
            }
        }

        $subjectsText = trim((string) ($data['subjects_text'] ?? ''));
        if ($subjectsText !== '') {
            $subjects = collect(preg_split('/[,;\n]/', $subjectsText))
                ->map(fn ($x) => trim($x))
                ->filter()
                ->values();

            foreach ($subjects as $i => $term) {
                $normalized = $this->normalizeLoose($term);
                $subject = Subject::query()->firstOrCreate(
                    ['normalized_term' => $normalized],
                    ['name' => $term, 'term' => $term, 'normalized_term' => $normalized, 'scheme' => 'local']
                );
                $biblio->subjects()->syncWithoutDetaching([
                    $subject->id => ['type' => 'topic', 'sort_order' => $i + 1],
                ]);
            }
        }

        $tagsText = trim((string) ($data['tags_text'] ?? ''));
        if ($tagsText !== '') {
            $tags = collect(preg_split('/[,;\n]/', $tagsText))
                ->map(fn ($x) => trim($x))
                ->filter()
                ->values();

            foreach ($tags as $i => $name) {
                $normalized = $this->normalizeLoose($name);
                $tag = Tag::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );
                $biblio->tags()->syncWithoutDetaching([
                    $tag->id => ['sort_order' => $i + 1],
                ]);
            }
        }

        $dcI18n = $this->normalizeDcI18nInput($request->input('dc_i18n'));
        $identifiers = $this->parseIdentifiersInput($request->input('identifiers'));
        $metadataService->syncMetadataForBiblio($biblio, $dcI18n, $identifiers);
        $aiCatalogingService->runForBiblio($biblio);

        $copiesCount = (int) ($data['copies_count'] ?? 0);
        if ($copiesCount > 0) {
            for ($i = 0; $i < $copiesCount; $i++) {
                Item::create([
                    'institution_id' => $institutionId,
                    'branch_id' => null,
                    'shelf_id' => null,
                    'biblio_id' => $biblio->id,
                    'barcode' => $this->generateUniqueCode('NB', 'barcode'),
                    'accession_number' => $this->generateUniqueCode('ACC', 'accession_number'),
                    'inventory_code' => null,
                    'status' => 'available',
                    'acquired_at' => null,
                    'price' => null,
                    'source' => null,
                    'notes' => null,
                ]);
            }
        }

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'create',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => [
                    'biblio_id' => (int) $biblio->id,
                    'title' => (string) $biblio->title,
                    'publisher' => (string) ($biblio->publisher ?? ''),
                    'isbn' => (string) ($biblio->isbn ?? ''),
                    'copies_created' => $copiesCount,
                    'ip' => (string) request()->ip(),
                    'user_agent' => (string) request()->userAgent(),
                ],
            ]);
        } catch (\Throwable) {
            // ignore
        }

        $redirect = redirect()
            ->route('katalog.show', $biblio->id)
            ->with('success', 'Bibliografi berhasil ditambahkan. Eksemplar: ' . $copiesCount);
        if (!empty($gate['warnings'])) {
            $redirect->with('warning', implode(' | ', (array) $gate['warnings']));
        }

        if ($coverError) {
            \Log::warning('Upload cover gagal (store): ' . $coverError);
            $redirect->with('warning', 'Cover gagal diupload. Coba file lain atau cek izin folder storage.');
        }

        return $redirect;
    }

    public function update(
        UpdateBiblioRequest $request,
        int $id,
        MetadataMappingService $metadataService,
        AiCatalogingService $aiCatalogingService
    ): RedirectResponse {
        $institutionId = $this->currentInstitutionId();

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'tags'])
            ->findOrFail($id);

        $data = $request->validated();
        $gate = ['ok' => true, 'errors' => [], 'warnings' => []];
        if ((bool) config('notobuku.catalog.quality_gate.enabled', true)) {
            /** @var \App\Services\CatalogQualityGateService $qualityGate */
            $qualityGate = app(\App\Services\CatalogQualityGateService::class);
            $gate = $qualityGate->evaluate($data, $institutionId, (int) $biblio->id);
            if (!$gate['ok']) {
                return back()
                    ->withInput()
                    ->withErrors(['quality_gate' => implode(' ', (array) ($gate['errors'] ?? []))]);
            }
        }

        $title = trim($data['title']);
        $subtitle = isset($data['subtitle']) ? trim((string) $data['subtitle']) : null;
        $subtitle = ($subtitle !== '' ? $subtitle : null);

        $biblio->title = $title;
        $biblio->subtitle = $subtitle;
        $biblio->normalized_title = $this->normalizeTitle($title, $subtitle);
        $biblio->responsibility_statement = isset($data['responsibility_statement']) ? (trim((string) $data['responsibility_statement']) ?: null) : null;
        $biblio->publisher = isset($data['publisher']) ? (trim((string) $data['publisher']) ?: null) : null;
        $biblio->place_of_publication = isset($data['place_of_publication']) ? (trim((string) $data['place_of_publication']) ?: null) : null;
        $biblio->publish_year = $data['publish_year'] ?? null;
        $biblio->isbn = isset($data['isbn']) ? (trim((string) $data['isbn']) ?: null) : null;
        $biblio->issn = isset($data['issn']) ? (trim((string) $data['issn']) ?: null) : null;
        $biblio->language = isset($data['language']) ? (trim((string) $data['language']) ?: 'id') : ($biblio->language ?: 'id');
        $biblio->material_type = isset($data['material_type']) ? (trim((string) $data['material_type']) ?: null) : ($biblio->material_type ?: null);
        $biblio->media_type = isset($data['media_type']) ? (trim((string) $data['media_type']) ?: null) : ($biblio->media_type ?: null);
        $biblio->edition = isset($data['edition']) ? (trim((string) $data['edition']) ?: null) : null;
        $biblio->physical_desc = isset($data['physical_desc']) ? (trim((string) $data['physical_desc']) ?: null) : null;
        $biblio->ddc = isset($data['ddc']) ? (trim((string) $data['ddc']) ?: null) : null;
        $biblio->call_number = isset($data['call_number']) ? (trim((string) $data['call_number']) ?: null) : null;
        $biblio->notes = isset($data['notes']) ? (trim((string) $data['notes']) ?: null) : null;
        $biblio->frequency = isset($data['frequency']) ? (trim((string) $data['frequency']) ?: null) : null;
        $biblio->former_frequency = isset($data['former_frequency']) ? (trim((string) $data['former_frequency']) ?: null) : null;
        $biblio->serial_beginning = isset($data['serial_beginning']) ? (trim((string) $data['serial_beginning']) ?: null) : null;
        $biblio->serial_ending = isset($data['serial_ending']) ? (trim((string) $data['serial_ending']) ?: null) : null;
        $biblio->serial_first_issue = isset($data['serial_first_issue']) ? (trim((string) $data['serial_first_issue']) ?: null) : null;
        $biblio->serial_last_issue = isset($data['serial_last_issue']) ? (trim((string) $data['serial_last_issue']) ?: null) : null;
        $biblio->serial_source_note = isset($data['serial_source_note']) ? (trim((string) $data['serial_source_note']) ?: null) : null;
        $biblio->serial_preceding_title = isset($data['serial_preceding_title']) ? (trim((string) $data['serial_preceding_title']) ?: null) : null;
        $biblio->serial_preceding_issn = isset($data['serial_preceding_issn']) ? (trim((string) $data['serial_preceding_issn']) ?: null) : null;
        $biblio->serial_succeeding_title = isset($data['serial_succeeding_title']) ? (trim((string) $data['serial_succeeding_title']) ?: null) : null;
        $biblio->serial_succeeding_issn = isset($data['serial_succeeding_issn']) ? (trim((string) $data['serial_succeeding_issn']) ?: null) : null;
        $biblio->holdings_summary = isset($data['holdings_summary']) ? (trim((string) $data['holdings_summary']) ?: null) : null;
        $biblio->holdings_supplement = isset($data['holdings_supplement']) ? (trim((string) $data['holdings_supplement']) ?: null) : null;
        $biblio->holdings_index = isset($data['holdings_index']) ? (trim((string) $data['holdings_index']) ?: null) : null;
        $biblio->save();

        $removeCover = (string)($request->input('remove_cover', '0')) === '1';
        $coverError = null;
        try {
            $file = $request->file('cover');
            \Log::info('Cover upload (update) debug', [
                'has_file' => $request->hasFile('cover'),
                'file_keys' => array_keys($request->allFiles() ?? []),
                'name' => $file?->getClientOriginalName(),
                'size' => $file?->getSize(),
                'error' => $file?->getError(),
                'mime' => $file?->getClientMimeType(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Cover upload (update) debug failed: ' . $e->getMessage());
        }
        if ($request->hasFile('cover')) {
            try {
                if (!empty($biblio->cover_path)) {
                    Storage::disk('public')->delete($biblio->cover_path);
                }
                $biblio->cover_path = $request->file('cover')->store('covers', 'public');
                $biblio->save();
            } catch (\Throwable $e) {
                $coverError = $e->getMessage();
            }
        } elseif ($removeCover) {
            try {
                if (!empty($biblio->cover_path)) {
                    Storage::disk('public')->delete($biblio->cover_path);
                }
            } catch (\Throwable) {
                // ignore
            }
            $biblio->cover_path = null;
            $biblio->save();
        }

        $useRoles = (string)($data['authors_role_mode'] ?? '0') === '1';
        $authorsRoles = $request->input('authors_roles_json');
        $syncAuthors = [];
        if ($useRoles && is_array($authorsRoles) && !empty($authorsRoles)) {
            $rows = collect($authorsRoles)
                ->filter(fn ($row) => is_array($row))
                ->map(function ($row) {
                    return [
                        'name' => trim((string)($row['name'] ?? '')),
                        'role' => trim((string)($row['role'] ?? 'aut')),
                    ];
                })
                ->filter(fn ($row) => $row['name'] !== '')
                ->values();

            foreach ($rows as $i => $row) {
                $name = $row['name'];
                $role = $row['role'] !== '' ? $row['role'] : 'aut';
                if ($role === 'pengarang') {
                    $role = 'aut';
                }
                $normalized = $this->normalizeLoose($name);
                $author = Author::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );
                $syncAuthors[$author->id] = ['role' => $role, 'sort_order' => $i + 1];
            }
        } else {
            $authors = collect(explode(',', (string) $data['authors_text']))
                ->map(fn ($x) => trim($x))
                ->filter()
                ->values();
            foreach ($authors as $i => $name) {
                $normalized = $this->normalizeLoose($name);
                $author = Author::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );
                $syncAuthors[$author->id] = ['role' => 'pengarang', 'sort_order' => $i + 1];
            }
        }
        $biblio->authors()->sync($syncAuthors);

        $subjectsText = trim((string) ($data['subjects_text'] ?? ''));
        $syncSubjects = [];
        if ($subjectsText !== '') {
            $subjects = collect(preg_split('/[,;\n]/', $subjectsText))
                ->map(fn ($x) => trim($x))
                ->filter()
                ->values();
            foreach ($subjects as $i => $term) {
                $normalized = $this->normalizeLoose($term);
                $subject = Subject::query()->firstOrCreate(
                    ['normalized_term' => $normalized],
                    ['name' => $term, 'term' => $term, 'normalized_term' => $normalized, 'scheme' => 'local']
                );
                $syncSubjects[$subject->id] = ['type' => 'topic', 'sort_order' => $i + 1];
            }
        }
        $biblio->subjects()->sync($syncSubjects);

        $tagsText = trim((string) ($data['tags_text'] ?? ''));
        $syncTags = [];
        if ($tagsText !== '') {
            $tags = collect(preg_split('/[,;\n]/', $tagsText))
                ->map(fn ($x) => trim($x))
                ->filter()
                ->values();
            foreach ($tags as $i => $name) {
                $normalized = $this->normalizeLoose($name);
                $tag = Tag::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );
                $syncTags[$tag->id] = ['sort_order' => $i + 1];
            }
        }
        $biblio->tags()->sync($syncTags);

        $dcI18n = $this->normalizeDcI18nInput($request->input('dc_i18n'));
        $identifiers = $this->parseIdentifiersInput($request->input('identifiers'));
        $metadataService->syncMetadataForBiblio($biblio, $dcI18n, $identifiers);
        $aiCatalogingService->runForBiblio($biblio);

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => [
                    'biblio_id' => (int) $biblio->id,
                    'title' => (string) $biblio->title,
                    'publisher' => (string) ($biblio->publisher ?? ''),
                    'isbn' => (string) ($biblio->isbn ?? ''),
                    'ip' => (string) request()->ip(),
                    'user_agent' => (string) request()->userAgent(),
                ],
            ]);
        } catch (\Throwable) {
            // ignore
        }

        $redirect = redirect()
            ->route('katalog.show', $biblio->id)
            ->with('success', 'Bibliografi berhasil diperbarui.');
        if (!empty($gate['warnings'])) {
            $redirect->with('warning', implode(' | ', (array) $gate['warnings']));
        }
        if ($coverError) {
            \Log::warning('Upload cover gagal (update): ' . $coverError);
            $redirect->with('warning', 'Cover gagal diupload. Coba file lain atau cek izin folder storage.');
        }

        return $redirect;
    }
}

