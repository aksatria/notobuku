<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateBiblioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],

            'responsibility_statement' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'place_of_publication' => ['nullable', 'string', 'max:255'],
            'publish_year' => ['nullable', 'integer', 'min:0', 'max:2100'],

            'isbn' => ['nullable', 'string', 'max:32', 'regex:/^[0-9Xx-]{10,20}$/'],
            'issn' => ['nullable', 'string', 'max:32'],

            'language' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2,3}(-[A-Z]{2})?$/'],
            'edition' => ['nullable', 'string', 'max:50'],
            'physical_desc' => ['nullable', 'string', 'max:255'],

            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_cover' => ['nullable', 'in:0,1'],

            'ddc' => ['nullable', 'string', 'max:32'],
            'call_number' => ['nullable', 'string', 'max:64'],

            'notes' => ['nullable', 'string'],

            'frequency' => ['nullable', 'string', 'max:80'],
            'former_frequency' => ['nullable', 'string', 'max:80'],
            'serial_beginning' => ['nullable', 'string', 'max:120'],
            'serial_ending' => ['nullable', 'string', 'max:120'],
            'serial_first_issue' => ['nullable', 'string', 'max:40'],
            'serial_last_issue' => ['nullable', 'string', 'max:40'],
            'serial_source_note' => ['nullable', 'string', 'max:255'],
            'serial_preceding_title' => ['nullable', 'string', 'max:255'],
            'serial_preceding_issn' => ['nullable', 'string', 'max:32'],
            'serial_succeeding_title' => ['nullable', 'string', 'max:255'],
            'serial_succeeding_issn' => ['nullable', 'string', 'max:32'],
            'holdings_summary' => ['nullable', 'string', 'max:255'],
            'holdings_supplement' => ['nullable', 'string', 'max:255'],
            'holdings_index' => ['nullable', 'string', 'max:255'],
            'auto_fix' => ['nullable', 'in:0,1'],

            'material_type' => ['nullable', 'string', 'max:32'],
            'media_type' => ['nullable', 'string', 'max:32'],

            'authors_text' => ['required', 'string', 'max:500'],
            'authors_role_mode' => ['nullable', 'in:0,1'],
            'authors_roles_json' => ['nullable', 'array'],
            'authors_roles_json.*.name' => ['nullable', 'string', 'max:255'],
            'authors_roles_json.*.role' => ['nullable', 'string', 'max:50'],
            'subjects_text' => ['nullable', 'string', 'max:800'],
            'tags_text' => ['nullable', 'string', 'max:500'],

            'dc_i18n' => ['nullable', 'array'],
            'dc_i18n.*' => ['nullable', 'array'],
            'dc_i18n.*.title' => ['nullable', 'string', 'max:255'],
            'dc_i18n.*.creator' => ['nullable'],
            'dc_i18n.*.creator.*' => ['string', 'max:255'],
            'dc_i18n.*.subject' => ['nullable'],
            'dc_i18n.*.subject.*' => ['string', 'max:255'],
            'dc_i18n.*.description' => ['nullable', 'string'],
            'dc_i18n.*.publisher' => ['nullable', 'string', 'max:255'],
            'dc_i18n.*.date' => ['nullable', 'string', 'max:32'],
            'dc_i18n.*.language' => ['nullable', 'string', 'max:10'],
            'dc_i18n.*.identifier' => ['nullable'],
            'dc_i18n.*.type' => ['nullable', 'string', 'max:32'],
            'dc_i18n.*.format' => ['nullable', 'string', 'max:32'],

            'identifiers' => ['nullable', 'array'],
            'identifiers.*.scheme' => ['nullable', 'string', 'max:50'],
            'identifiers.*.value' => ['nullable', 'string', 'max:255'],
            'identifiers.*.uri' => ['nullable', 'string', 'max:512'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        foreach (['dc_i18n', 'identifiers', 'authors_roles_json'] as $key) {
            if (!$this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if ($value === '' || $value === 'null') {
                $prepared[$key] = null;
                continue;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $prepared[$key] = $decoded;
                }
            }
        }

        if ($this->has('identifiers')) {
            $identifiers = $prepared['identifiers'] ?? $this->input('identifiers');
            if (is_array($identifiers)) {
                $filtered = array_values(array_filter($identifiers, function ($row) {
                    if (!is_array($row)) {
                        return false;
                    }
                    $scheme = trim((string)($row['scheme'] ?? ''));
                    $value = trim((string)($row['value'] ?? ''));
                    $uri = trim((string)($row['uri'] ?? ''));
                    return $scheme !== '' || $value !== '' || $uri !== '';
                }));
                $prepared['identifiers'] = $filtered ?: null;
            }
        }

        if ($this->has('authors_roles_json')) {
            $authorsRoles = $prepared['authors_roles_json'] ?? $this->input('authors_roles_json');
            if (is_array($authorsRoles)) {
                $filtered = array_values(array_filter($authorsRoles, function ($row) {
                    if (!is_array($row)) {
                        return false;
                    }
                    $name = trim((string)($row['name'] ?? ''));
                    $role = trim((string)($row['role'] ?? ''));
                    return $name !== '' || $role !== '';
                }));
                $prepared['authors_roles_json'] = $filtered ?: null;
            }
        }

        $autoFix = (string) $this->input('auto_fix', '1') === '1';
        if ($autoFix) {
            foreach ([
                'title', 'subtitle', 'responsibility_statement', 'publisher', 'place_of_publication',
                'edition', 'series_title', 'physical_desc', 'extent', 'dimensions', 'illustrations',
                'frequency', 'former_frequency', 'serial_beginning', 'serial_ending', 'serial_first_issue',
                'serial_last_issue', 'serial_source_note', 'serial_preceding_title', 'serial_succeeding_title',
                'holdings_summary', 'holdings_supplement', 'holdings_index',
            ] as $key) {
                if ($this->has($key)) {
                    $prepared[$key] = $this->normalizeTextField($this->input($key));
                }
            }
            if ($this->has('language')) {
                $lang = strtolower(trim((string) $this->input('language')));
                $lang = str_replace('_', '-', $lang);
                $prepared['language'] = $this->normalizeLangCode($lang);
            }
            if ($this->has('isbn')) {
                $isbn = preg_replace('/\s+/', '', (string) $this->input('isbn'));
                $isbn = strtoupper((string) $isbn);
                $prepared['isbn'] = trim((string) $isbn) !== '' ? $isbn : null;
            }
            if ($this->has('issn')) {
                $issn = preg_replace('/\s+/', '', (string) $this->input('issn'));
                $issn = strtoupper((string) $issn);
                $prepared['issn'] = trim((string) $issn) !== '' ? $issn : null;
            }
            foreach (['serial_preceding_issn', 'serial_succeeding_issn'] as $key) {
                if ($this->has($key)) {
                    $issn = preg_replace('/\s+/', '', (string) $this->input($key));
                    $issn = strtoupper((string) $issn);
                    $prepared[$key] = trim((string) $issn) !== '' ? $issn : null;
                }
            }
            if ($this->has('ddc')) {
                $normalized = $this->normalizeDdc((string) $this->input('ddc'));
                $prepared['ddc'] = $normalized ?? trim((string) $this->input('ddc'));
            }
            if ($this->has('call_number')) {
                $call = strtoupper(trim((string) $this->input('call_number')));
                $call = preg_replace('/\s+/', ' ', $call ?? '');
                $prepared['call_number'] = $call !== '' ? $call : null;
            }
            if ($this->has('authors_text')) {
                $authors = trim((string) $this->input('authors_text'));
                $authors = preg_replace('/\s*,\s*/', ', ', $authors ?? '');
                $prepared['authors_text'] = $authors;
            }
            if ($this->has('subjects_text')) {
                $subjects = trim((string) $this->input('subjects_text'));
                $subjects = preg_replace('/\s*;\s*/', '; ', $subjects ?? '');
                $prepared['subjects_text'] = $subjects;
            }
            if ($this->has('tags_text')) {
                $tags = trim((string) $this->input('tags_text'));
                $tags = preg_replace('/\s*,\s*/', ', ', $tags ?? '');
                $prepared['tags_text'] = $tags;
            }
        }

        if (!empty($prepared)) {
            $this->merge($prepared);
        }
    }

    private function normalizeTextField($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') return null;
        $v = preg_replace('/\s+/', ' ', $v ?? '');
        return trim((string) $v);
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

    private function normalizeLangCode(string $value): ?string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '') return null;
        $primary = explode('-', $v)[0] ?? $v;

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

        if (isset($map[$primary])) {
            return $map[$primary];
        }
        if (strlen($primary) === 3) {
            return $primary;
        }
        if (strlen($primary) > 3) {
            return substr($primary, 0, 3);
        }
        return $primary !== '' ? $primary : null;
    }

    protected function passedValidation(): void
    {
        try {
            $file = $this->file('cover');
            Log::info('Cover upload (formrequest update) debug', [
                'has_file' => $this->hasFile('cover'),
                'file_keys' => array_keys($this->allFiles() ?? []),
                'name' => $file?->getClientOriginalName(),
                'size' => $file?->getSize(),
                'error' => $file?->getError(),
                'mime' => $file?->getClientMimeType(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cover upload (formrequest update) debug failed: ' . $e->getMessage());
        }
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        try {
            Log::warning('Cover upload (formrequest update) validation failed', [
                'errors' => $validator->errors()->all(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
        parent::failedValidation($validator);
    }
}
