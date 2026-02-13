<?php

namespace App\Services;

use App\Models\Biblio;
use App\Models\MarcSetting;
use App\Models\AuthorityAuthor;
use App\Models\AuthoritySubject;
use App\Models\AuthorityPublisher;
use App\Services\MarcControlFieldBuilder;
use App\Services\MarcPolicyService;

class MarcValidationService
{
    private MarcControlFieldBuilder $controlBuilder;
    private MarcPolicyService $policyService;
    private array $policyRules = [];

    public function __construct()
    {
        $this->controlBuilder = new MarcControlFieldBuilder();
        $this->policyService = new MarcPolicyService();
    }
    public function validateForExport(Biblio $biblio): array
    {
        $errors = [];
        $biblio->loadMissing(['authors', 'subjects', 'identifiers']);
        $this->policyRules = $this->resolvePolicyRules($biblio->institution_id ?? null);

        if (empty($biblio->title)) {
            $errors[] = '245$a (title) wajib diisi.';
        }
        if (empty($biblio->place_of_publication)) {
            $errors[] = '264$a (place_of_publication) wajib diisi.';
        }
        if (empty($biblio->publish_year) || !preg_match('/^\d{4}$/', (string) $biblio->publish_year)) {
            $errors[] = '264$c (publish_year) wajib 4 digit.';
        }
        $lang = $this->normalizeLangCode($biblio->language);
        if ($lang === 'und' || strlen($lang) !== 3) {
            $errors[] = '041$a / 008 bahasa wajib 3 huruf.';
        }

        $controlFields = $this->controlBuilder->buildControlFields($biblio);
        $field008 = $controlFields['008'] ?? '';
        if (strlen($field008) !== 40) {
            $errors[] = '008 harus 40 karakter.';
        }
        $place = substr($field008, 15, 3);
        if (strlen($place) !== 3) {
            $errors[] = '008 place code harus 3 karakter.';
        }

        $hasAuthor = ($biblio->authors?->count() ?? 0) > 0;
        $hasPublisher = !empty($biblio->publisher);
        if (!$hasAuthor && !$hasPublisher) {
            $errors[] = 'Minimal ada access point (1xx/7xx/110/710/111/711).';
        }
        if (!$hasPublisher) {
            $this->pushPolicyIssue($errors, 'publisher_missing', '264$b (publisher) sebaiknya diisi.');
        }
        if (empty($biblio->material_type)) {
            $this->pushPolicyIssue($errors, 'material_type_missing', '336 (content type) sebaiknya diisi.');
        }
        if (empty($biblio->media_type)) {
            $this->pushPolicyIssue($errors, 'media_type_missing', '337 (media type) sebaiknya diisi.');
        }
        if (empty($biblio->call_number) && empty($biblio->ddc)) {
            $this->pushPolicyIssue($errors, 'call_number_missing', '090 (call number) sebaiknya diisi.');
        }
        if (!empty($biblio->isbn) && !$this->isIsbnValid((string) $biblio->isbn)) {
            $this->pushPolicyIssue($errors, 'isbn_invalid', 'ISBN format tidak valid.');
        }
        if (!empty($biblio->issn) && !$this->isIssnValid((string) $biblio->issn)) {
            $this->pushPolicyIssue($errors, 'issn_invalid', 'ISSN format tidak valid.');
        }
        if (!$this->controlBuilder->isOnlineResource($biblio)) {
            if (empty($biblio->physical_desc) && empty($biblio->extent)) {
                $this->pushPolicyIssue($errors, 'physical_desc_missing', '300 (physical description/extent) sebaiknya diisi untuk material fisik.');
            }
        }

        if (!empty($biblio->title) && $this->hasLeadingPunctuation($biblio->title)) {
            $this->pushPolicyIssue($errors, 'title_leading_punctuation', '245$a diawali tanda baca; cek title proper.');
        }

        if ($this->controlBuilder->isOnlineResource($biblio)) {
            $hasUri = $biblio->identifiers?->contains(fn($id) => in_array($id->scheme, ['uri', 'url'], true) || !empty($id->uri)) ?? false;
            if (!$hasUri) {
                $errors[] = '856 wajib diisi untuk material online.';
            }
        }

        if (!empty($biblio->ddc)) {
            $normalized = $this->normalizeDdc((string) $biblio->ddc);
            $base = $this->extractDdcBase($normalized ?? (string) $biblio->ddc);
            $mode = $this->getDdcValidationMode($biblio->institution_id ?? null);
            if ($normalized === null) {
                $this->pushDdcIssue($errors, $mode, '082$a tidak valid (format DDC).');
            } elseif ($base === '' || !\App\Models\DdcClass::query()->where('code', $base)->exists()) {
                $this->pushDdcIssue($errors, $mode, '082$a tidak dikenal / belum ada di master DdcClass.');
            }
            if (empty($biblio->call_number)) {
                $this->pushPolicyIssue($errors, 'ddc_missing_call_number', '090 kosong; pertimbangkan isi call number (mis. berdasarkan DDC).');
            }
        }

        $cf006 = $controlFields['006'] ?? '';
        $cf007 = $controlFields['007'] ?? '';
        $type006 = strtolower(trim(substr($cf006, 0, 1)));
        $type007 = strtolower(trim(substr($cf007, 0, 2)));
        $this->lintControlFieldMismatch($errors, $type006, $type007);
        $isAudio = $type006 === 'i' || $type006 === 'j' || str_starts_with($type007, 'sd');
        if ($isAudio) {
            $hasNarrator = $biblio->authors?->contains(function ($author) {
                $role = strtolower(trim((string) ($author->pivot?->role ?? $author->role ?? '')));
                return str_contains($role, 'narator') || str_contains($role, 'narrator');
            }) ?? false;
            if (!$hasNarrator) {
                $this->pushPolicyIssue($errors, 'audio_missing_narrator', 'Audio: sebaiknya ada relator narrator (narator/narrator).');
            }
        }

        if ($this->isSerialRecord($biblio) && empty($biblio->frequency)) {
            $this->pushPolicyIssue($errors, 'serial_frequency_missing', 'Serial: 310 (current frequency) sebaiknya diisi.');
        }
        if ($this->isSerialRecord($biblio)) {
            if (strlen($field008) >= 20) {
                if (trim((string) ($field008[18] ?? '')) === '' || trim((string) ($field008[19] ?? '')) === '') {
                    $this->pushPolicyIssue($errors, 'serial_008_missing', 'Serial: 008/18-19 (frequency/regularity) sebaiknya terisi.');
                }
            }
            if (strlen($field008) >= 24) {
                $typeCode = trim((string) ($field008[21] ?? ''));
                if ($typeCode === '') {
                    $this->pushPolicyIssue($errors, 'serial_008_detail_missing', 'Serial: 008/21 (type of continuing resource) sebaiknya terisi.');
                }
                $form = (string) ($field008[23] ?? '');
                if ($this->controlBuilder->isOnlineResource($biblio) && $form !== 'o') {
                    $this->pushPolicyIssue($errors, 'serial_008_detail_missing', 'Serial: 008/23 seharusnya "o" untuk resource online.');
                }
                if (!$this->controlBuilder->isOnlineResource($biblio) && $form === 'o') {
                    $this->pushPolicyIssue($errors, 'serial_008_detail_missing', 'Serial: 008/23 "o" tapi material tidak online.');
                }
            }
            if (empty($biblio->serial_beginning)) {
                $this->pushPolicyIssue($errors, 'serial_362_missing', 'Serial: 362 (dates of publication) sebaiknya diisi.');
            }
            if (empty($biblio->serial_source_note)) {
                $this->pushPolicyIssue($errors, 'serial_588_missing', 'Serial: 588 (source of description) sebaiknya diisi.');
            }
        }

        $relatorWarnings = $this->validateRelators($biblio);
        foreach ($relatorWarnings as $warn) {
            $this->pushPolicyIssue($errors, 'relator_uncontrolled', $warn);
        }

        $this->validateRelatorPresence($errors, $biblio);
        $this->validateAuthorityCoverage($errors, $biblio);
        $this->validateSubjectSchemes($errors, $biblio);
        $this->validateNameDateFormat($errors, $biblio);

        return $errors;
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

    private function isIsbnValid(string $value): bool
    {
        $v = trim($value);
        if ($v === '') return false;
        return (bool) preg_match('/^[0-9Xx-]{10,20}$/', $v);
    }

    private function isIssnValid(string $value): bool
    {
        $v = trim($value);
        if ($v === '') return false;
        return (bool) preg_match('/^\d{4}-?\d{3}[\dXx]$/', $v);
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

    private function getDdcValidationMode(?int $institutionId): string
    {
        $fallback = (string) config('marc.ddc_rules.validation_mode', 'warn');
        $mode = $this->getMarcSettingScalar('ddc_rules.validation_mode', $fallback, $institutionId);
        $mode = strtolower(trim((string) $mode));
        return in_array($mode, ['warn', 'error'], true) ? $mode : $fallback;
    }

    private function getMarcSettingScalar(string $key, string $fallback, ?int $institutionId): string
    {
        $row = MarcSetting::query()->where('key', $key)->first();
        $val = $row?->value_json;
        if (is_array($val)) {
            if ($institutionId !== null && isset($val['institutions'][$institutionId])) {
                $ins = $val['institutions'][$institutionId];
                if (is_string($ins) && trim($ins) !== '') {
                    return $ins;
                }
            }
            $v = $val['value'] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return $v;
            }
        } elseif (is_string($val) && trim($val) !== '') {
            return $val;
        }
        return $fallback;
    }

    private function pushDdcIssue(array &$errors, string $mode, string $message): void
    {
        if ($mode === 'error') {
            $errors[] = $message;
        } else {
            $errors[] = 'WARN: ' . $message;
        }
    }

    private function validateRelators(Biblio $biblio): array
    {
        $allowedTerms = array_map('strtolower', (array) config('marc.relators.terms', []));
        $allowedCodes = array_map('strtolower', (array) config('marc.relators.codes', []));
        $allowedTerms = array_filter(array_map('trim', $allowedTerms));
        $allowedCodes = array_filter(array_map('trim', $allowedCodes));

        $warnings = [];
        $seen = [];
        foreach ($biblio->authors ?? [] as $author) {
            $role = strtolower(trim((string) ($author->pivot?->role ?? $author->role ?? '')));
            if ($role === '') {
                continue;
            }

            if ($this->isRoleCategory($role)) {
                continue;
            }

            if (strlen($role) === 3 && in_array($role, $allowedCodes, true)) {
                continue;
            }

            if (in_array($role, $allowedTerms, true)) {
                continue;
            }

            if (!isset($seen[$role])) {
                $warnings[] = 'Relator tidak dikenal: "' . $role . '". Gunakan MARC relator term/code.';
                $seen[$role] = true;
            }
        }

        return $warnings;
    }

    private function validateRelatorPresence(array &$errors, Biblio $biblio): void
    {
        foreach ($biblio->authors ?? [] as $author) {
            $role = trim((string) ($author->pivot?->role ?? $author->role ?? ''));
            if ($role === '') {
                $this->pushPolicyIssue($errors, 'relator_missing', 'Relator kosong; tambahkan relator term/code.');
                return;
            }
        }
    }

    private function validateAuthorityCoverage(array &$errors, Biblio $biblio): void
    {
        $biblio->loadMissing(['authors', 'subjects']);

        foreach ($biblio->authors ?? [] as $author) {
            $name = trim((string) ($author->name ?? ''));
            if ($name === '') continue;
            $normalized = $this->normalizeName($name);
            if ($normalized === '') continue;
            $record = AuthorityAuthor::query()->where('normalized_name', $normalized)->first();
            if (!$record) {
                $this->pushPolicyIssue($errors, 'authority_missing', 'Authority tidak ditemukan untuk author: "' . $name . '".');
                break;
            }
            if ($this->authorityDedupStrict() && AuthorityAuthor::query()->where('normalized_name', $normalized)->count() > 1) {
                $this->pushPolicyIssue($errors, 'authority_dedup', 'Authority duplikat untuk author: "' . $name . '".');
            }
            if (!$this->authorityHasUri($record?->external_ids ?? null)) {
                $this->pushPolicyIssue($errors, 'authority_uri_missing', 'Authority author tanpa URI: "' . $name . '".');
            }
        }

        foreach ($biblio->subjects ?? [] as $subject) {
            $term = $subject->term ?? $subject->name ?? '';
            $term = trim((string) $term);
            if ($term === '') continue;
            $scheme = strtolower(trim((string) ($subject->scheme ?? 'local')));
            if ($scheme === '') $scheme = 'local';
            $normalized = $this->normalizeName($term);
            if ($normalized === '') continue;
            $record = AuthoritySubject::query()
                ->where('scheme', $scheme)
                ->where('normalized_term', $normalized)
                ->first();
            if (!$record) {
                $this->pushPolicyIssue($errors, 'authority_missing', 'Authority tidak ditemukan untuk subject: "' . $term . '".');
                break;
            }
            if ($this->authorityDedupStrict() && AuthoritySubject::query()->where('scheme', $scheme)->where('normalized_term', $normalized)->count() > 1) {
                $this->pushPolicyIssue($errors, 'authority_dedup', 'Authority duplikat untuk subject: "' . $term . '".');
            }
            if (!$this->authorityHasUri($record?->external_ids ?? null)) {
                $this->pushPolicyIssue($errors, 'authority_uri_missing', 'Authority subject tanpa URI: "' . $term . '".');
            }
        }

        if (!empty($biblio->publisher)) {
            $publisher = trim((string) $biblio->publisher);
            if ($publisher !== '') {
                $normalized = $this->normalizeName($publisher);
                $record = AuthorityPublisher::query()->where('normalized_name', $normalized)->first();
                if (!$record) {
                    $this->pushPolicyIssue($errors, 'authority_missing', 'Authority tidak ditemukan untuk publisher: "' . $publisher . '".');
                } elseif (!$this->authorityHasUri($record?->external_ids ?? null)) {
                    $this->pushPolicyIssue($errors, 'authority_uri_missing', 'Authority publisher tanpa URI: "' . $publisher . '".');
                } elseif ($this->authorityDedupStrict() && AuthorityPublisher::query()->where('normalized_name', $normalized)->count() > 1) {
                    $this->pushPolicyIssue($errors, 'authority_dedup', 'Authority duplikat untuk publisher: "' . $publisher . '".');
                }
            }
        }
    }

    private function validateSubjectSchemes(array &$errors, Biblio $biblio): void
    {
        $allowed = array_map('strtolower', (array) config('marc.subject_schemes', ['local', 'lcsh']));
        foreach ($biblio->subjects ?? [] as $subject) {
            $scheme = strtolower(trim((string) ($subject->scheme ?? 'local')));
            if ($scheme === '') $scheme = 'local';
            if (!in_array($scheme, $allowed, true)) {
                $this->pushPolicyIssue($errors, 'subject_scheme_unknown', 'Subject scheme tidak dikenal: "' . $scheme . '".');
                return;
            }
        }
    }

    private function validateNameDateFormat(array &$errors, Biblio $biblio): void
    {
        foreach ($biblio->authors ?? [] as $author) {
            $name = trim((string) ($author->name ?? ''));
            if ($name === '') continue;
            if (preg_match('/\\d/', $name) && !preg_match('/\\b\\d{4}(-\\d{4}|-)\\b/', $name)) {
                $this->pushPolicyIssue($errors, 'name_date_format', 'Nama dengan tanggal harus format YYYY- atau YYYY-YYYY: "' . $name . '".');
                return;
            }
        }
    }

    private function hasLeadingPunctuation(string $value): bool
    {
        $value = ltrim($value);
        return (bool) preg_match('/^[\\s\\"\\\'\\(\\)\\[\\]\\{\\}\\<\\>\\.,:;\\-\\–\\—]+/u', $value);
    }

    private function normalizeName(string $value): string
    {
        $v = strtolower(trim($value));
        $v = preg_replace('/[^\\p{L}\\p{N}\\s]/u', ' ', $v);
        $v = preg_replace('/\\s+/', ' ', (string) $v);
        return trim($v);
    }

    private function lintControlFieldMismatch(array &$errors, string $type006, string $type007): void
    {
        if ($type006 === '') return;

        $expected = '';
        if ($type006 === 'j' || $type006 === 'i') {
            $expected = 'sd';
        } elseif ($type006 === 'g') {
            $expected = 'v';
        } elseif ($type006 === 'm') {
            $expected = 'cr';
        } elseif (in_array($type006, ['e', 'f'], true)) {
            $expected = 'aj';
        } elseif (in_array($type006, ['a', 't'], true)) {
            $expected = 't';
        }

        if ($expected !== '' && $type007 !== '' && !str_starts_with($type007, $expected)) {
            $this->pushPolicyIssue($errors, 'control_field_mismatch', 'Konsistensi 006/007 lemah: 006=' . $type006 . ' tetapi 007=' . $type007 . '.');
        }
    }

    private function authorityHasUri($externalIds): bool
    {
        if (!is_array($externalIds)) {
            return false;
        }
        $external = array_change_key_case($externalIds, CASE_LOWER);
        foreach (['lcnaf', 'viaf', 'isni', 'wikidata', 'uri'] as $key) {
            $val = $external[$key] ?? null;
            if (is_string($val) && trim($val) !== '') {
                return true;
            }
        }
        return false;
    }

    private function authorityDedupStrict(): bool
    {
        return (bool) config('marc.authority_dedup_strict', false);
    }

    private function isRoleCategory(string $role): bool
    {
        $role = trim(strtolower($role));
        if ($role === '') return false;

        $categoryKeywords = [
            'corporate', 'organization', 'organisasi', 'lembaga', 'instansi',
            'meeting', 'conference', 'seminar', 'symposium', 'simposium', 'kongres', 'konferensi', 'workshop',
        ];

        foreach ($categoryKeywords as $kw) {
            if (str_contains($role, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function resolvePolicyRules(?int $institutionId = null): array
    {
        $policy = $this->policyService->getActivePolicy($institutionId);
        $rules = $policy['rules'] ?? [];
        return is_array($rules) ? $rules : [];
    }

    private function pushPolicyIssue(array &$errors, string $ruleKey, string $message): void
    {
        $severity = strtolower(trim((string) ($this->policyRules[$ruleKey] ?? 'warn')));
        if ($severity === 'error') {
            $errors[] = $message;
            return;
        }
        $errors[] = 'WARN: ' . $message;
    }

    private function isSerialRecord(Biblio $biblio): bool
    {
        $leader = $this->controlBuilder->buildLeader($biblio);
        return isset($leader[7]) && $leader[7] === 's';
    }
}
