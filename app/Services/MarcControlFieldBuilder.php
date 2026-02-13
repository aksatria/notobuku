<?php

namespace App\Services;

use App\Models\Biblio;
use App\Models\MarcSetting;

class MarcControlFieldBuilder
{
    public function buildLeader(Biblio $biblio): string
    {
        $leader = '00000nam a2200000 a 4500';
        $haystack = $this->buildMediaHaystack($biblio);
        $profile = $this->resolveMediaProfile($biblio);

        $type = 'a';
        $bibLevel = 'm';
        if (str_contains($haystack, 'serial') || str_contains($haystack, 'jurnal') || str_contains($haystack, 'journal')) {
            $type = 's';
            $bibLevel = 's';
        } else {
            $type = $this->resolveLeaderTypeFromProfile($profile);
        }

        if (strlen($leader) >= 7) {
            $leader[6] = $type;
        }
        if (strlen($leader) >= 8) {
            $leader[7] = $bibLevel;
        }

        return $leader;
    }

    public function build006(Biblio $biblio): string
    {
        $profile = $this->resolveMediaProfile($biblio);
        return $this->build006FromProfile($profile);
    }

    public function build007(Biblio $biblio): string
    {
        $profile = $this->resolveMediaProfile($biblio);
        return $this->build007FromProfile($profile);
    }

    public function build008(Biblio $biblio): string
    {
        $profile = $this->resolveMediaProfile($biblio);
        return $this->build008FromProfile($biblio, $profile);
    }

    public function buildControlFields(Biblio $biblio): array
    {
        $profile = $this->resolveMediaProfile($biblio);
        return [
            '006' => $this->build006FromProfile($profile),
            '007' => $this->build007FromProfile($profile),
            '008' => $this->build008FromProfile($biblio, $profile),
        ];
    }

    public function getMediaProfile(Biblio $biblio): array
    {
        return $this->resolveMediaProfile($biblio);
    }

    private function build006FromProfile(array $profile): string
    {
        $pattern = $profile['pattern_006'] ?? null;
        if (is_string($pattern) && $pattern !== '') {
            return str_pad(substr($pattern, 0, 18), 18, ' ');
        }
        $type = $profile['type_006'] ?? 'a';
        return $type . str_repeat(' ', 17);
    }

    private function build007FromProfile(array $profile): string
    {
        $pattern = $profile['pattern_007'] ?? null;
        $value = '';
        if (is_string($pattern) && $pattern !== '') {
            $value = $pattern;
        } else {
            $value = $profile['type_007'] ?? 'ta';
        }

        $min = max(2, (int) ($profile['min_007'] ?? 2));
        if (strlen($value) < $min) {
            $value = str_pad($value, $min, ' ');
        }

        return $value;
    }

    private function build008FromProfile(Biblio $biblio, array $profile): string
    {
        $entered = now()->format('ymd');
        $status = 's';
        $date1 = $biblio->publish_year ? str_pad((string) $biblio->publish_year, 4, ' ', STR_PAD_RIGHT) : '    ';
        $date2 = '    ';
        $place = $this->mapPlaceCode($biblio->place_of_publication);
        $lang = $this->normalizeLangCode($biblio->language);

        $pattern = $this->resolvePattern008($biblio, $profile);
        if (!is_string($pattern) || $pattern === '') {
            $pattern = '{entered}{status}{date1}{date2}{place}                {lang}  ';
        }

        $field = strtr($pattern, [
            '{entered}' => $entered,
            '{status}' => $status,
            '{date1}' => $date1,
            '{date2}' => $date2,
            '{place}' => $place,
            '{lang}' => $lang,
        ]);

        $field = substr(str_pad($field, 40, ' '), 0, 40);

        $formOfItem = $profile['form_of_item'] ?? null;
        $audienceCode = $this->mapAudienceCode($biblio);
        if ($audienceCode !== null && $audienceCode !== '') {
            $field[22] = $audienceCode[0];
        }
        if ($formOfItem !== null && is_string($formOfItem) && $formOfItem !== '') {
            $field[23] = $formOfItem[0];
        } elseif ($this->isOnlineResource($biblio)) {
            $field[23] = 'o';
        }

        if ($this->isSerial($biblio)) {
            $freq = $this->mapSerialFrequency($biblio->frequency ?? null);
            if (isset($field[18]) && $field[18] === ' ') {
                $field[18] = $freq;
            }
            if (isset($field[19]) && $field[19] === ' ') {
                $field[19] = $freq !== 'u' ? 'r' : 'u';
            }
            $serialType = $this->mapSerialTypeCode($biblio);
            if ($serialType !== '' && isset($field[21]) && trim((string) $field[21]) === '') {
                $field[21] = $serialType;
            }
            if (isset($field[23]) && $field[23] === ' ') {
                $form = $this->mapSerialFormOfItem($biblio);
                if ($form !== '') {
                    $field[23] = $form;
                }
            }
            if (isset($field[34]) && $field[34] === ' ') {
                $field[34] = '0';
            }
        }

        return $field;
    }

    public function isOnlineResource(Biblio $biblio): bool
    {
        $material = strtolower(trim((string) $biblio->material_type));
        $media = strtolower(trim((string) $biblio->media_type));
        $mode = $this->getOnlineDetectionMode($biblio->institution_id ?? null);

        $biblio->loadMissing('identifiers');
        $identifiers = $biblio->identifiers ?? [];

        $hasUriScheme = function () use ($identifiers): bool {
            foreach ($identifiers as $id) {
                $scheme = strtolower(trim((string) ($id->scheme ?? '')));
                $value = trim((string) ($id->value ?? ''));
                $uri = trim((string) ($id->uri ?? ''));
                if (in_array($scheme, ['uri', 'url'], true) && ($value !== '' || $uri !== '')) {
                    return true;
                }
            }
            return false;
        };

        if ($mode === 'strict') {
            return $hasUriScheme();
        }

        if (str_contains($material, 'ebook') || str_contains($media, 'online') || str_contains($media, 'computer') || str_contains($media, 'digital')) {
            return true;
        }

        if ($hasUriScheme()) {
            return true;
        }

        foreach ($identifiers as $id) {
            $uri = trim((string) ($id->uri ?? ''));
            if ($uri !== '') {
                return true;
            }
        }

        return false;
    }

    private function resolvePattern008(Biblio $biblio, array $profile): ?string
    {
        $type006 = strtolower(trim((string) ($profile['type_006'] ?? '')));
        $type007 = strtolower(trim((string) ($profile['type_007'] ?? '')));
        $byType = $this->resolvePattern008ByType($type006, $type007, $profile);
        if ($byType) {
            return $byType;
        }

        $material = strtolower(trim((string) $biblio->material_type));
        $media = strtolower(trim((string) $biblio->media_type));
        $haystack = trim($material . ' ' . $media);

        if ($this->isAudiobookHaystack($haystack)) {
            return $profile['pattern_008_audio'] ?? $profile['pattern_008'] ?? null;
        }
        if (str_contains($haystack, 'ebook') || str_contains($haystack, 'online') || str_contains($haystack, 'computer')) {
            return $profile['pattern_008_cf'] ?? $profile['pattern_008'] ?? null;
        }
        if (str_contains($haystack, 'video') || str_contains($haystack, 'dvd') || str_contains($haystack, 'film')) {
            return $profile['pattern_008_visual'] ?? $profile['pattern_008'] ?? null;
        }
        if (str_contains($haystack, 'music') || str_contains($haystack, 'musik')) {
            return $profile['pattern_008_music'] ?? $profile['pattern_008'] ?? null;
        }
        if (str_contains($haystack, 'audio') || str_contains($haystack, 'sound')) {
            return $profile['pattern_008_audio'] ?? $profile['pattern_008'] ?? null;
        }
        if (str_contains($haystack, 'map') || str_contains($haystack, 'atlas') || str_contains($haystack, 'kartografi')) {
            return $profile['pattern_008_map'] ?? $profile['pattern_008'] ?? null;
        }

        return $profile['pattern_008_books'] ?? $profile['pattern_008'] ?? null;
    }

    private function resolvePattern008ByType(string $type006, string $type007, array $profile): ?string
    {
        if ($type006 === 'j') {
            return $profile['pattern_008_music'] ?? $profile['pattern_008'] ?? null;
        }
        if ($type006 === 'i') {
            return $profile['pattern_008_audio'] ?? $profile['pattern_008'] ?? null;
        }
        if ($type006 === 'g') {
            return $profile['pattern_008_visual'] ?? $profile['pattern_008'] ?? null;
        }
        if ($type006 === 'm') {
            return $profile['pattern_008_cf'] ?? $profile['pattern_008'] ?? null;
        }
        if (in_array($type006, ['e', 'f'], true)) {
            return $profile['pattern_008_map'] ?? $profile['pattern_008'] ?? null;
        }
        if (in_array($type006, ['a', 't'], true)) {
            return $profile['pattern_008_books'] ?? $profile['pattern_008'] ?? null;
        }

        if (str_starts_with($type007, 'sd')) {
            return $type006 === 'j'
                ? ($profile['pattern_008_music'] ?? $profile['pattern_008'] ?? null)
                : ($profile['pattern_008_audio'] ?? $profile['pattern_008'] ?? null);
        }
        if (str_starts_with($type007, 'v')) {
            return $profile['pattern_008_visual'] ?? $profile['pattern_008'] ?? null;
        }
        if (str_starts_with($type007, 'cr')) {
            return $profile['pattern_008_cf'] ?? $profile['pattern_008'] ?? null;
        }
        if (str_starts_with($type007, 'aj')) {
            return $profile['pattern_008_map'] ?? $profile['pattern_008'] ?? null;
        }

        return null;
    }

    private function resolveMediaProfile(Biblio $biblio): array
    {
        $haystack = $this->buildMediaHaystack($biblio);

        $profiles = $this->getMarcSetting('media_profiles', (array) config('marc.media_profiles', []));
        if ($this->isAudiobookHaystack($haystack)) {
            $audioProfile = $this->pickAudioProfile($profiles);
            if (!empty($audioProfile)) {
                return $audioProfile;
            }
        }
        if ($this->isMusicHaystack($haystack)) {
            $musicProfile = $this->pickMusicProfile($profiles);
            if (!empty($musicProfile)) {
                return $musicProfile;
            }
        }
        foreach ($profiles as $profile) {
            if (!is_array($profile)) continue;
            $keywords = (array) ($profile['keywords'] ?? []);
            usort($keywords, fn($a, $b) => strlen((string) $b) <=> strlen((string) $a));
            foreach ($keywords as $kw) {
                $kw = strtolower(trim((string) $kw));
                if ($kw === '') continue;
                if ($this->matchesKeyword($haystack, $kw)) {
                    return $profile;
                }
            }
        }

        return ['type_006' => 'a', 'type_007' => 'ta'];
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

        return preg_replace('/\s+/', ' ', trim($haystack));
    }

    private function isSerial(Biblio $biblio): bool
    {
        $haystack = $this->buildMediaHaystack($biblio);
        return str_contains($haystack, 'serial')
            || str_contains($haystack, 'jurnal')
            || str_contains($haystack, 'journal')
            || str_contains($haystack, 'periodical')
            || str_contains($haystack, 'periodik')
            || str_contains($haystack, 'majalah')
            || str_contains($haystack, 'newspaper')
            || str_contains($haystack, 'koran')
            || str_contains($haystack, 'database')
            || str_contains($haystack, 'website')
            || str_contains($haystack, 'web');
    }

    private function resolveLeaderTypeFromProfile(array $profile): string
    {
        $name = strtolower(trim((string) ($profile['name'] ?? '')));
        $type006 = strtolower(trim((string) ($profile['type_006'] ?? '')));
        $type007 = strtolower(trim((string) ($profile['type_007'] ?? '')));
        $keywords = array_map(fn($v) => strtolower(trim((string) $v)), (array) ($profile['keywords'] ?? []));
        $keywords = array_filter($keywords, fn($v) => $v !== '');
        $joined = ' ' . implode(' ', $keywords) . ' ';

        if ($type006 === 'i' || $type006 === 'j' || str_starts_with($type007, 'sd') || str_contains($name, 'audio') || str_contains($joined, ' audio ')) {
            return $type006 === 'j' ? 'j' : 'i';
        }
        if (str_starts_with($type007, 'v') || str_contains($name, 'video') || str_contains($joined, ' video ') || str_contains($joined, ' film ') || str_contains($joined, ' dvd ')) {
            return 'g';
        }
        if (in_array($type006, ['e', 'f'], true) || str_contains($name, 'map') || str_contains($joined, ' map ') || str_contains($joined, ' atlas ') || str_contains($joined, ' kartografi ')) {
            return 'e';
        }
        if ($type006 === 'm' || str_contains($name, 'computer') || str_contains($joined, ' ebook ') || str_contains($joined, ' online ') || str_contains($joined, ' computer ') || str_contains($joined, ' digital ')) {
            return 'm';
        }

        return 'a';
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

    private function mapSerialFrequency(?string $value): string
    {
        $raw = strtolower(trim((string) $value));
        if ($raw === '') return 'u';

        $map = (array) config('marc.serial_frequency_codes', []);
        foreach ($map as $label => $code) {
            $label = strtolower(trim((string) $label));
            if ($label !== '' && str_contains($raw, $label)) {
                $code = strtolower(trim((string) $code));
                return $code !== '' ? $code[0] : 'u';
            }
        }

        return 'u';
    }

    private function mapSerialTypeCode(Biblio $biblio): string
    {
        $material = strtolower(trim((string) $biblio->material_type));
        $media = strtolower(trim((string) $biblio->media_type));
        $haystack = $this->buildMediaHaystack($biblio);

        $overrideMap = (array) config('marc.serial_type_overrides', []);
        foreach ($overrideMap as $needle => $code) {
            $needle = strtolower(trim((string) $needle));
            $code = strtolower(trim((string) $code));
            if ($needle === '' || $code === '') continue;
            if (str_contains($material, $needle) || str_contains($media, $needle)) {
                return $code[0];
            }
        }

        $map = (array) config('marc.serial_type_codes', []);
        foreach ($map as $code => $keywords) {
            $code = strtolower(trim((string) $code));
            if ($code === '') continue;
            foreach ((array) $keywords as $kw) {
                $kw = strtolower(trim((string) $kw));
                if ($kw !== '' && str_contains($haystack, $kw)) {
                    return $code[0];
                }
            }
        }

        if ($this->isOnlineResource($biblio) && (str_contains($haystack, 'web') || str_contains($haystack, 'website'))) {
            return 'w';
        }

        return 'p';
    }

    private function mapSerialFormOfItem(Biblio $biblio): string
    {
        $material = strtolower(trim((string) $biblio->material_type));
        $media = strtolower(trim((string) $biblio->media_type));
        $map = (array) config('marc.serial_form_of_item_map', []);
        foreach ($map as $needle => $code) {
            $needle = strtolower(trim((string) $needle));
            $code = strtolower(trim((string) $code));
            if ($needle === '' || $code === '') continue;
            if (str_contains($material, $needle) || str_contains($media, $needle)) {
                return $code[0];
            }
        }
        if ($this->isOnlineResource($biblio)) {
            return 'o';
        }
        return '';
    }

    private function mapPlaceCode(?string $place): string
    {
        $place = strtolower(trim((string) $place));
        if ($place === '') {
            return 'xx ';
        }

        $normalize = function (string $value): string {
            $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
            $value = preg_replace('/\s+/', ' ', (string) $value);
            return trim($value);
        };

        $p = $normalize($place);
        $padded = ' ' . $p . ' ';

        $cityToCountryRaw = $this->getMarcSetting('place_codes_city', (array) config('marc.place_codes.city', []));
        $cityToCountry = $this->normalizePlaceMap($cityToCountryRaw, $normalize);

        foreach ($cityToCountry as $city => $code) {
            if (str_contains($padded, ' ' . $city . ' ')) {
                return $this->formatPlaceCode($code);
            }
        }

        $countryToCodeRaw = $this->getMarcSetting('place_codes_country', (array) config('marc.place_codes.country', []));
        $strict = (bool) config('marc.place_codes.strict_country_codes', false);
        $countryToCode = $this->normalizePlaceMap($countryToCodeRaw, $normalize);

        foreach ($countryToCode as $country => $code) {
            if (str_contains($padded, ' ' . $country . ' ')) {
                $formatted = $this->formatPlaceCode($code);
                if ($strict && !$this->isValidCountryCode($formatted)) {
                    return 'xx ';
                }
                return $formatted;
            }
        }

        return 'xx ';
    }

    private function normalizePlaceMap(array $map, callable $normalize): array
    {
        $normalized = [];
        foreach ($map as $key => $code) {
            if (!is_string($key) || !is_string($code)) {
                continue;
            }
            $k = $normalize(strtolower($key));
            if ($k === '') continue;
            $normalized[$k] = $code;
        }

        uksort($normalized, function ($a, $b) {
            $len = strlen($b) <=> strlen($a);
            return $len !== 0 ? $len : strcmp($a, $b);
        });

        return $normalized;
    }

    private function formatPlaceCode(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '') return 'xx ';
        if (strlen($code) === 2) return $code . ' ';
        if (strlen($code) >= 3) return substr($code, 0, 3);
        return str_pad($code, 3, ' ');
    }

    private function isValidCountryCode(string $code): bool
    {
        return (bool) preg_match('/^[a-z]{2}\s$|^[a-z]{3}$/', $code);
    }

    private function mapAudienceCode(Biblio $biblio): ?string
    {
        $raw = strtolower(trim((string) ($biblio->audience ?? $biblio->audience_note ?? '')));
        if ($raw === '') return null;

        $matches = function (array $needles) use ($raw): bool {
            foreach ($needles as $n) {
                if (str_contains($raw, $n)) return true;
            }
            return false;
        };

        if ($matches(['prasekolah', 'preschool'])) return 'a';
        if ($matches(['sd', 'sekolah dasar', 'elementary'])) return 'b';
        if ($matches(['smp', 'sma', 'sekolah menengah', 'secondary'])) return 'c';
        if ($matches(['remaja', 'teen', 'teenager', 'young adult', 'youth'])) return 'd';
        if ($matches(['dewasa', 'adult'])) return 'e';
        if ($matches(['khusus', 'specialized', 'spesialis', 'akademik'])) return 'f';
        if ($matches(['umum', 'general', 'public'])) return 'g';
        if ($matches(['anak', 'children', 'child', 'kids'])) return 'j';

        return null;
    }

    private function isAudiobookHaystack(string $haystack): bool
    {
        return str_contains($haystack, 'audiobook') || str_contains($haystack, 'audio book');
    }

    private function isMusicHaystack(string $haystack): bool
    {
        return str_contains($haystack, 'music')
            || str_contains($haystack, 'musik')
            || str_contains($haystack, 'soundtrack')
            || str_contains($haystack, 'ost');
    }

    private function pickAudioProfile(array $profiles): array
    {
        foreach ($profiles as $profile) {
            if (!is_array($profile)) continue;
            $name = strtolower(trim((string) ($profile['name'] ?? '')));
            $type006 = strtolower(trim((string) ($profile['type_006'] ?? '')));
            $type007 = strtolower(trim((string) ($profile['type_007'] ?? '')));
            if ($type006 === 'i' && str_starts_with($type007, 'sd')) {
                return $profile;
            }
            if (in_array($name, ['audio', 'audio_nonmusic', 'audio_non_music'], true)) {
                return $profile;
            }
        }

        return [];
    }

    private function pickMusicProfile(array $profiles): array
    {
        foreach ($profiles as $profile) {
            if (!is_array($profile)) continue;
            $name = strtolower(trim((string) ($profile['name'] ?? '')));
            $type006 = strtolower(trim((string) ($profile['type_006'] ?? '')));
            $type007 = strtolower(trim((string) ($profile['type_007'] ?? '')));
            if ($type006 === 'j' && str_starts_with($type007, 'sd')) {
                return $profile;
            }
            if (str_contains($name, 'music') || str_contains($name, 'musik') || $name === 'audio_music') {
                return $profile;
            }
        }

        return [];
    }

    private function matchesKeyword(string $haystack, string $keyword): bool
    {
        $escaped = preg_quote($keyword, '/');
        return (bool) preg_match('/(^|[\s\p{P}])' . $escaped . '([\s\p{P}]|$)/u', $haystack);
    }

    private function getOnlineDetectionMode(?int $institutionId): string
    {
        $fallback = (string) config('marc.online_detection_mode', 'strict');
        $mode = $this->getMarcSettingScalar('online_detection_mode', $fallback, $institutionId);
        return in_array($mode, ['strict', 'loose'], true) ? $mode : $fallback;
    }

    private function getMarcSetting(string $key, array $fallback): array
    {
        try {
            $row = MarcSetting::query()->where('key', $key)->first();
            if (is_array($row?->value_json) && !empty($row->value_json)) {
                if ($key === 'media_profiles') {
                    $profiles = array_merge($row->value_json, $fallback);
                    return $this->migrateMediaProfiles($profiles);
                }
                return array_merge($fallback, $row->value_json);
            }
        } catch (\Throwable $e) {
            // ignore db errors and use fallback
        }

        return $fallback;
    }

    private function getMarcSettingScalar(string $key, string $fallback, ?int $institutionId): string
    {
        try {
            $row = MarcSetting::query()->where('key', $key)->first();
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
            // ignore db errors
        }

        return $fallback;
    }

    private function migrateMediaProfiles(array $profiles): array
    {
        $out = [];
        foreach ($profiles as $row) {
            if (!is_array($row)) {
                $out[] = $row;
                continue;
            }
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $type006 = strtolower(trim((string) ($row['type_006'] ?? '')));
            $type007 = strtolower(trim((string) ($row['type_007'] ?? '')));
            $isMusic = $type006 === 'j'
                || str_contains($name, 'music')
                || str_contains($name, 'musik');
            $isAudio = $type006 === 'i'
                || (str_contains($name, 'audio') && $type006 !== 'j')
                || (str_starts_with($type007, 'sd') && $type006 !== 'j');

            if ($isMusic && empty($row['pattern_008_music'])) {
                $row['pattern_008_music'] = $row['pattern_008']
                    ?? '{entered}{status}{date1}{date2}{place}                {lang}  ';
            }
            if ($isAudio && empty($row['pattern_008_audio'])) {
                $row['pattern_008_audio'] = $row['pattern_008']
                    ?? '{entered}{status}{date1}{date2}{place}                {lang}  ';
            }
            $out[] = $row;
        }

        return $out;
    }
}
