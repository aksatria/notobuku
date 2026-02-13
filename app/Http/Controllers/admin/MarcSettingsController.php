<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MarcSetting;
use App\Models\MarcPolicySet;
use App\Services\ExportService;
use App\Services\MarcPolicyService;
use Illuminate\Http\Request;
use App\Models\User;

class MarcSettingsController extends Controller
{
    public function index(Request $request, MarcPolicyService $policyService)
    {
        $defaults = [
            'place_codes_city' => (array) config('marc.place_codes.city', []),
            'place_codes_country' => (array) config('marc.place_codes.country', []),
            'media_profiles' => (array) config('marc.media_profiles', []),
            'ddc_edition' => (string) config('marc.ddc_edition', '23'),
            'ddc_rules_validation_mode' => (string) config('marc.ddc_rules.validation_mode', 'warn'),
            'online_detection_mode' => (string) config('marc.online_detection_mode', 'strict'),
        ];

        $overrides = [
            'place_codes_city' => $this->getSetting('place_codes_city'),
            'place_codes_country' => $this->getSetting('place_codes_country'),
            'media_profiles' => $this->getSetting('media_profiles'),
            'ddc_edition' => $this->getSettingScalar('ddc_edition'),
            'ddc_rules_validation_mode' => $this->getSettingScalar('ddc_rules.validation_mode'),
            'online_detection_mode' => $this->getSettingScalar('online_detection_mode'),
        ];
        if (!empty($overrides['media_profiles'])) {
            [$migrated, $changed] = $this->migrateMediaProfiles($overrides['media_profiles']);
            if ($changed) {
                $this->saveSetting('media_profiles', $migrated);
                $overrides['media_profiles'] = $migrated;
            }
        }

        $role = auth()->user()->role ?? 'member';
        $institutionId = (int) (auth()->user()->institution_id ?? 0) ?: null;
        $policyScope = $this->resolvePolicyScope($request, $role);
        $policyInstitutionId = $policyScope === 'global' ? null : $institutionId;
        if ($policyScope === 'institution' && $policyInstitutionId === null) {
            $policyScope = 'global';
            $policyInstitutionId = null;
        }
        $canGlobalPolicy = $role === 'super_admin';
        $policyDraft = MarcPolicySet::query()
            ->where('status', 'draft')
            ->when($policyInstitutionId === null, fn($q) => $q->whereNull('institution_id'))
            ->when($policyInstitutionId !== null, fn($q) => $q->where('institution_id', $policyInstitutionId))
            ->orderByDesc('version')
            ->first();
        $policyPublished = MarcPolicySet::query()
            ->where('status', 'published')
            ->when($policyInstitutionId === null, fn($q) => $q->whereNull('institution_id'))
            ->when($policyInstitutionId !== null, fn($q) => $q->where('institution_id', $policyInstitutionId))
            ->orderByDesc('version')
            ->first();
        $policyDefault = $policyService->getActivePolicy($policyInstitutionId);
        $policyPresets = [
            'balanced' => (array) config('marc.policy_packs.rda_core', []),
        ];
        $policyPresets['strict'] = $this->buildStrictPolicyPreset($policyPresets['balanced']);
        $policyPresets['strict_institution'] = $this->buildInstitutionStrictPolicyPreset($policyPresets['balanced']);
        $policyDiff = $this->diffPolicyPayload(
            $policyDraft?->payload_json ?? null,
            $policyPublished?->payload_json ?? null,
            $policyService
        );
        $auditsQuery = AuditLog::query()->where('format', 'marc_policy');
        if ($policyInstitutionId === null) {
            $auditsQuery->whereNull('meta->institution_id');
        } else {
            $includeGlobal = $request->query('include_global', '1') !== '0';
            $auditsQuery->where(function ($q) use ($policyInstitutionId, $includeGlobal) {
                if ($includeGlobal) {
                    $q->whereNull('meta->institution_id')
                      ->orWhere('meta->institution_id', $policyInstitutionId);
                } else {
                    $q->where('meta->institution_id', $policyInstitutionId);
                }
            });
        }
        $start = $this->parseDate($request->query('start_date'));
        $end = $this->parseDate($request->query('end_date'));
        if ($start) {
            $auditsQuery->whereDate('created_at', '>=', $start);
        }
        if ($end) {
            $auditsQuery->whereDate('created_at', '<=', $end);
        }
        $action = trim((string) $request->query('action', ''));
        if ($action !== '') {
            $auditsQuery->where('action', $action);
        }
        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $auditsQuery->where('status', $status);
        }
        $audits = $auditsQuery->orderByDesc('id')->limit(25)->get();
        $auditUserIds = $audits->pluck('user_id')->filter()->unique()->values();
        $auditUsers = $auditUserIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $auditUserIds)->get()->keyBy('id');

        return view('admin.marc-settings', [
            'defaults' => $defaults,
            'overrides' => $overrides,
            'policyDraft' => $policyDraft,
            'policyPublished' => $policyPublished,
            'policyDefault' => $policyDefault,
            'policyPresets' => $policyPresets,
            'policyDiff' => $policyDiff,
            'policyAudits' => $audits,
            'policyAuditUsers' => $auditUsers,
            'policyScope' => $policyScope,
            'canGlobalPolicy' => $canGlobalPolicy,
            'auditFilters' => [
                'start_date' => $start,
                'end_date' => $end,
                'include_global' => $request->query('include_global', '1'),
                'action' => $action,
                'status' => $status,
            ],
        ]);
    }

    private function resolvePolicyScope(Request $request, ?string $role = null): string
    {
        $role = $role ?? (auth()->user()->role ?? 'member');
        $scope = strtolower(trim((string) $request->input('policy_scope', 'institution')));
        if ($role !== 'super_admin') {
            return 'institution';
        }
        return $scope === 'global' ? 'global' : 'institution';
    }

    private function buildStrictPolicyPreset(array $base): array
    {
        $payload = $base;
        $payload['name'] = $payload['name'] ?? 'RDA Core';
        $rules = (array) ($payload['rules'] ?? []);
        foreach ([
            'publisher_missing',
            'material_type_missing',
            'media_type_missing',
            'isbn_invalid',
            'issn_invalid',
            'call_number_missing',
            'relator_missing',
            'authority_missing',
        ] as $key) {
            $rules[$key] = 'error';
        }
        $payload['rules'] = $rules;
        return $payload;
    }

    private function buildInstitutionStrictPolicyPreset(array $base): array
    {
        $payload = $this->buildStrictPolicyPreset($base);
        $payload['name'] = 'RDA Core (Strict - Institusi)';
        $rules = (array) ($payload['rules'] ?? []);
        foreach ([
            'authority_uri_missing',
            'control_field_mismatch',
            'subject_scheme_unknown',
            'title_leading_punctuation',
            'name_date_format',
            'serial_008_missing',
            'serial_008_detail_missing',
            'serial_362_missing',
            'serial_588_missing',
        ] as $key) {
            $rules[$key] = 'error';
        }
        $payload['rules'] = $rules;
        return $payload;
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'place_codes_city' => ['nullable', 'string'],
            'place_codes_country' => ['nullable', 'string'],
            'media_profiles' => ['nullable', 'string'],
            'ddc_edition' => ['nullable', 'string', 'max:10'],
            'ddc_rules_validation_mode' => ['nullable', 'string', 'in:warn,error'],
            'online_detection_mode' => ['nullable', 'string', 'in:strict,loose'],
        ]);

        $city = $this->decodeJson($data['place_codes_city'] ?? '');
        $country = $this->decodeJson($data['place_codes_country'] ?? '');
        $profiles = $this->decodeJson($data['media_profiles'] ?? '');

        if ($city === false || $country === false || $profiles === false) {
            $errors = [];
            if ($city === false) $errors['place_codes_city'] = 'JSON tidak valid.';
            if ($country === false) $errors['place_codes_country'] = 'JSON tidak valid.';
            if ($profiles === false) $errors['media_profiles'] = 'JSON tidak valid.';
            return back()->withInput()->withErrors($errors);
        }

        $errors = [];
        if (!$this->validatePlaceCodes($city)) {
            $errors['place_codes_city'] = 'Place Codes City harus berupa JSON object: key string → value string (2-3 char).';
        }
        if (!$this->validatePlaceCodes($country)) {
            $errors['place_codes_country'] = 'Place Codes Country harus berupa JSON object: key string → value string (2-3 char).';
        }
        if (!$this->validateMediaProfiles($profiles)) {
            $errors['media_profiles'] = 'Media Profiles harus berupa JSON array objek: name, keywords[], type_006, type_007.';
        }
        if (!empty($errors)) {
            return back()->withInput()->withErrors($errors);
        }

        $this->saveSetting('place_codes_city', $city);
        $this->saveSetting('place_codes_country', $country);
        [$profiles, $changed] = $this->migrateMediaProfiles($profiles);
        $this->saveSetting('media_profiles', $profiles);
        $this->saveSettingScalar('ddc_edition', trim((string) ($data['ddc_edition'] ?? '')));
        $this->saveSettingScalar('ddc_rules.validation_mode', trim((string) ($data['ddc_rules_validation_mode'] ?? '')));
        $this->saveSettingScalar('online_detection_mode', trim((string) ($data['online_detection_mode'] ?? '')));

        return back()->with('success', 'Pengaturan MARC berhasil disimpan.');
    }

    public function savePolicyDraft(Request $request, MarcPolicyService $policyService)
    {
        $data = $request->validate([
            'policy_name' => ['nullable', 'string', 'max:120'],
            'policy_payload' => ['nullable', 'string'],
        ]);

        $payload = $this->decodeJson($data['policy_payload'] ?? '');
        if ($payload === false || !is_array($payload)) {
            return back()->withInput()->withErrors(['policy_payload' => 'JSON tidak valid.']);
        }
        $policyErrors = $policyService->validatePayload($payload);
        if (!empty($policyErrors)) {
            return back()->withInput()->withErrors(['policy_payload' => implode(' ', $policyErrors)]);
        }

        $name = trim((string) ($data['policy_name'] ?? 'RDA Core'));
        if ($name === '') {
            $name = 'RDA Core';
        }

        $policyScope = $this->resolvePolicyScope($request);
        $institutionId = $policyScope === 'global'
            ? null
            : ((int) (auth()->user()->institution_id ?? 0) ?: null);
        $userId = auth()->user()?->id;

        $policyService->createDraftPolicy($name, $payload, $institutionId, $userId);

        return back()->with('success', 'Policy MARC disimpan sebagai draft.');
    }

    public function publishPolicy(Request $request, MarcPolicyService $policyService)
    {
        $data = $request->validate([
            'policy_id' => ['required', 'integer'],
        ]);

        $policy = MarcPolicySet::query()->findOrFail((int) $data['policy_id']);
        $policyScope = $this->resolvePolicyScope($request);
        $institutionId = $policyScope === 'global'
            ? null
            : ((int) (auth()->user()->institution_id ?? 0) ?: null);
        if ($institutionId !== ($policy->institution_id ?? null)) {
            return back()->withErrors(['policy_id' => 'Tidak berhak publish policy ini.']);
        }
        $policyService->publishPolicy($policy->id, auth()->user()?->id);

        return back()->with('success', 'Policy MARC berhasil dipublish.');
    }

    public function reset()
    {
        MarcSetting::query()->whereIn('key', [
            'place_codes_city',
            'place_codes_country',
            'media_profiles',
            'ddc_edition',
            'ddc_rules.validation_mode',
            'online_detection_mode',
        ])->delete();

        return back()->with('success', 'Pengaturan MARC direset ke default.');
    }

    public function preview(Request $request, ExportService $exportService)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'variant_title' => ['nullable', 'string', 'max:255'],
            'former_title' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'responsibility_statement' => ['nullable', 'string', 'max:255'],
            'contents_note' => ['nullable', 'string', 'max:1000'],
            'citation_note' => ['nullable', 'string', 'max:500'],
            'audience_note' => ['nullable', 'string', 'max:255'],
            'language_note' => ['nullable', 'string', 'max:255'],
            'local_note' => ['nullable', 'string', 'max:500'],
            'subjects' => ['nullable', 'string', 'max:500'],
            'subject_scheme' => ['nullable', 'string', 'in:local,lcsh'],
            'subject_type' => ['nullable', 'string', 'in:topic,person,corporate,meeting,uniform,geographic'],
            'author_role' => ['nullable', 'string', 'max:50'],
            'author_role_custom' => ['nullable', 'string', 'max:50'],
            'meeting_names' => ['nullable', 'string', 'max:500'],
            'meeting_ind1' => ['nullable', 'string', 'in: ,0,1,2'],
            'force_meeting_main' => ['nullable', 'boolean'],
            'place_of_publication' => ['nullable', 'string', 'max:255'],
            'publish_year' => ['nullable', 'integer', 'min:1000', 'max:9999'],
            'material_type' => ['nullable', 'string', 'max:50'],
            'media_type' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        if (!empty($data['meeting_ind1']) && empty($data['meeting_names'])) {
            return response('meeting_ind1 hanya berlaku jika meeting_names diisi.', 422);
        }

        if (empty($data['meeting_names']) && !empty($data['force_meeting_main'])) {
            $data['force_meeting_main'] = false;
        }

        $institutionId = (int) (auth()->user()->institution_id ?? 0);
        $institutionCode = null;
        if ($institutionId > 0) {
            $institutionCode = \Illuminate\Support\Facades\DB::table('institutions')
                ->where('id', $institutionId)->value('code');
        }

        $xml = $exportService->buildMarcPreview($data, $institutionCode);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function decodeJson(string $input): array|bool
    {
        $input = trim($input);
        if ($input === '') {
            return [];
        }

        $decoded = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return false;
        }

        return $decoded;
    }

    private function getSetting(string $key): array
    {
        $row = MarcSetting::query()->where('key', $key)->first();
        return is_array($row?->value_json) ? $row->value_json : [];
    }

    private function saveSetting(string $key, array $value): void
    {
        MarcSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value_json' => $value]
        );
    }

    private function getSettingScalar(string $key): ?string
    {
        $row = MarcSetting::query()->where('key', $key)->first();
        $val = $row?->value_json;
        if (is_string($val)) return $val;
        if (is_array($val)) {
            $v = $val['value'] ?? null;
            return is_string($v) ? $v : null;
        }
        return null;
    }

    private function saveSettingScalar(string $key, string $value): void
    {
        if ($value === '') {
            MarcSetting::query()->where('key', $key)->delete();
            return;
        }
        MarcSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value_json' => ['value' => $value]]
        );
    }

    private function validatePlaceCodes(array $value): bool
    {
        foreach ($value as $k => $v) {
            if (!is_string($k) || trim($k) === '') return false;
            if (!is_string($v) || trim($v) === '') return false;
            $len = strlen(trim($v));
            if ($len < 2 || $len > 3) return false;
        }
        return true;
    }

    private function validateMediaProfiles(array $profiles): bool
    {
        foreach ($profiles as $row) {
            if (!is_array($row)) return false;
            $name = $row['name'] ?? '';
            $keywords = $row['keywords'] ?? null;
            $type006 = $row['type_006'] ?? '';
            $type007 = $row['type_007'] ?? '';
            $pattern006 = $row['pattern_006'] ?? '';
            $pattern007 = $row['pattern_007'] ?? '';
            $pattern008 = $row['pattern_008'] ?? null;
            $min007 = isset($row['min_007']) ? (int) $row['min_007'] : 2;
            $pattern008Variants = [];
            foreach ($row as $k => $v) {
                if (str_starts_with((string) $k, 'pattern_008_')) {
                    $pattern008Variants[] = $v;
                }
            }

            if (!is_string($name) || trim($name) === '') return false;
            if (!is_array($keywords) || empty($keywords)) return false;
            foreach ($keywords as $kw) {
                if (!is_string($kw) || trim($kw) === '') return false;
            }
            $has006 = (is_string($pattern006) && strlen($pattern006) >= 1) || (is_string($type006) && strlen(trim($type006)) === 1);
            $has007 = (is_string($pattern007) && strlen($pattern007) >= max(2, $min007)) || (is_string($type007) && strlen(trim($type007)) >= 2);
            if (!$has006 || !$has007) return false;
            if (is_string($pattern006) && $pattern006 !== '' && strlen($pattern006) !== 18) return false;
            if (is_string($pattern007) && $pattern007 !== '' && strlen($pattern007) < max(2, $min007)) return false;
            if (is_string($pattern008) && $pattern008 !== '' && strlen($pattern008) !== 40) return false;
            foreach ($pattern008Variants as $variant) {
                if (is_string($variant) && $variant !== '' && strlen($variant) !== 40) return false;
            }
        }
        return true;
    }

    private function migrateMediaProfiles(array $profiles): array
    {
        $changed = false;
        $out = [];
        foreach ($profiles as $row) {
            if (!is_array($row)) {
                $out[] = $row;
                continue;
            }
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $type006 = strtolower(trim((string) ($row['type_006'] ?? '')));
            $type007 = strtolower(trim((string) ($row['type_007'] ?? '')));
            $isAudio = $type006 === 'i'
                || (str_contains($name, 'audio') && $type006 !== 'j')
                || (str_starts_with($type007, 'sd') && $type006 !== 'j');

            if ($isAudio && empty($row['pattern_008_audio'])) {
                $row['pattern_008_audio'] = $row['pattern_008_music']
                    ?? $row['pattern_008']
                    ?? '{entered}{status}{date1}{date2}{place}                {lang}  ';
                $changed = true;
            }
            $out[] = $row;
        }

        return [$out, $changed];
    }

    private function diffPolicyPayload(?array $draft, ?array $published, MarcPolicyService $policyService): array
    {
        if (!is_array($draft)) $draft = [];
        if (!is_array($published)) $published = [];

        $draft = $policyService->normalizePayload($draft);
        $published = $policyService->normalizePayload($published);

        $rows = [];
        if (($draft['schema_version'] ?? null) !== ($published['schema_version'] ?? null)) {
            $rows[] = [
                'rule' => 'schema_version',
                'published' => $published['schema_version'] ?? '-',
                'draft' => $draft['schema_version'] ?? '-',
                'severity_change' => '',
                'published_json' => json_encode(['schema_version' => $published['schema_version'] ?? null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'draft_json' => json_encode(['schema_version' => $draft['schema_version'] ?? null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }

        $draftRules = (array) ($draft['rules'] ?? []);
        $publishedRules = (array) ($published['rules'] ?? []);
        $allKeys = array_unique(array_merge(array_keys($draftRules), array_keys($publishedRules)));
        sort($allKeys);
        foreach ($allKeys as $key) {
            $from = $publishedRules[$key] ?? '-';
            $to = $draftRules[$key] ?? '-';
            if ($from !== $to) {
                $severityChange = '';
                if ($from === 'warn' && $to === 'error') {
                    $severityChange = 'up';
                } elseif ($from === 'error' && $to === 'warn') {
                    $severityChange = 'down';
                }
                $changeType = ($from === '-') ? 'added' : (($to === '-') ? 'removed' : 'changed');
                $rows[] = [
                    'rule' => $key,
                    'published' => $from,
                    'draft' => $to,
                    'severity_change' => $severityChange,
                    'change_type' => $changeType,
                    'published_json' => json_encode([$key => $from], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'draft_json' => json_encode([$key => $to], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return $rows;
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $ts = strtotime($value);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
    }
}
