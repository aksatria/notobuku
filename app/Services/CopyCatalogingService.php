<?php

namespace App\Services;

use App\Models\Biblio;
use App\Models\CopyCatalogImport;
use App\Models\CopyCatalogSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CopyCatalogingService
{
    public function search(CopyCatalogSource $source, string $query, int $limit = 10): array
    {
        $query = trim($query);
        $limit = max(1, min(30, $limit));
        if ($query === '') {
            return [];
        }

        return match ($source->protocol) {
            'sru' => $this->searchSru($source, $query, $limit),
            'p2p' => $this->searchP2p($source, $query, $limit),
            'z3950' => $this->searchZ3950Gateway($source, $query, $limit),
            default => [],
        };
    }

    public function importRecord(CopyCatalogSource $source, int $institutionId, int $userId, array $record): CopyCatalogImport
    {
        $title = trim((string) ($record['title'] ?? ''));
        if ($title === '') {
            return CopyCatalogImport::query()->create([
                'institution_id' => $institutionId,
                'user_id' => $userId,
                'source_id' => $source->id,
                'external_id' => (string) ($record['external_id'] ?? ''),
                'title' => null,
                'status' => 'failed',
                'error_message' => 'Judul kosong dari record eksternal.',
                'raw_json' => $record,
            ]);
        }

        $isbn = trim((string) ($record['isbn'] ?? ''));
        $publishYear = preg_replace('/[^0-9]/', '', (string) ($record['publish_year'] ?? ''));
        if (strlen($publishYear) > 4) {
            $publishYear = substr($publishYear, 0, 4);
        }

        $biblio = Biblio::query()->create([
            'institution_id' => $institutionId,
            'title' => $title,
            'normalized_title' => Str::of($title)->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString(),
            'subtitle' => trim((string) ($record['subtitle'] ?? '')) ?: null,
            'isbn' => $isbn !== '' ? $isbn : null,
            'publisher' => trim((string) ($record['publisher'] ?? '')) ?: null,
            'publish_year' => is_numeric($publishYear) ? (int) $publishYear : null,
            'language' => trim((string) ($record['language'] ?? '')) ?: null,
            'call_number' => trim((string) ($record['call_number'] ?? '')) ?: null,
            'notes' => trim((string) ($record['notes'] ?? '')) ?: ('Copy cataloging import dari ' . $source->name),
        ]);

        return CopyCatalogImport::query()->create([
            'institution_id' => $institutionId,
            'user_id' => $userId,
            'source_id' => $source->id,
            'biblio_id' => $biblio->id,
            'external_id' => (string) ($record['external_id'] ?? ''),
            'title' => $title,
            'status' => 'imported',
            'raw_json' => $record,
        ]);
    }

    private function searchSru(CopyCatalogSource $source, string $query, int $limit): array
    {
        $resp = Http::timeout(12)->get($source->endpoint, [
            'version' => '1.2',
            'operation' => 'searchRetrieve',
            'query' => $query,
            'maximumRecords' => $limit,
            'recordSchema' => 'dc',
        ]);

        if (!$resp->ok()) {
            return [];
        }

        try {
            $xml = simplexml_load_string((string) $resp->body());
            if ($xml === false) {
                return [];
            }

            $records = $xml->xpath('//*[local-name()="record"]');
            if (!is_array($records)) {
                return [];
            }

            $rows = [];
            foreach ($records as $r) {
                $title = $this->firstXpathValue($r, './/*[local-name()="title"]');
                $creator = $this->firstXpathValue($r, './/*[local-name()="creator"]');
                $publisher = $this->firstXpathValue($r, './/*[local-name()="publisher"]');
                $date = $this->firstXpathValue($r, './/*[local-name()="date"]');
                $identifier = $this->firstXpathValue($r, './/*[local-name()="identifier"]');
                if ($title === '') {
                    continue;
                }
                $rows[] = [
                    'external_id' => $identifier !== '' ? $identifier : md5($title . $creator . $date),
                    'title' => $title,
                    'author' => $creator,
                    'publisher' => $publisher,
                    'publish_year' => $date,
                    'isbn' => $this->extractIsbn($identifier),
                    'source_protocol' => 'sru',
                ];
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    private function firstXpathValue(\SimpleXMLElement $node, string $path): string
    {
        $res = $node->xpath($path);
        if (!is_array($res) || !isset($res[0])) {
            return '';
        }
        return trim((string) $res[0]);
    }

    private function searchP2p(CopyCatalogSource $source, string $query, int $limit): array
    {
        $resp = Http::timeout(12)->get($source->endpoint, [
            'q' => $query,
            'limit' => $limit,
        ]);
        if (!$resp->ok()) {
            return [];
        }
        $json = $resp->json();
        $rows = (array) ($json['data'] ?? $json['records'] ?? []);
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $title = trim((string) ($r['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $out[] = [
                'external_id' => (string) ($r['id'] ?? $r['external_id'] ?? md5($title)),
                'title' => $title,
                'author' => trim((string) ($r['author'] ?? '')),
                'publisher' => trim((string) ($r['publisher'] ?? '')),
                'publish_year' => trim((string) ($r['publish_year'] ?? '')),
                'isbn' => trim((string) ($r['isbn'] ?? '')),
                'source_protocol' => 'p2p',
            ];
        }
        return $out;
    }

    private function searchZ3950Gateway(CopyCatalogSource $source, string $query, int $limit): array
    {
        $settings = (array) ($source->settings_json ?? []);
        $gateway = trim((string) ($settings['gateway_url'] ?? $source->endpoint));
        if ($gateway === '') {
            return [];
        }

        $resp = Http::timeout(12)->get($gateway, [
            'q' => $query,
            'limit' => $limit,
            'format' => 'json',
        ]);

        if (!$resp->ok()) {
            return [];
        }

        $rows = (array) ($resp->json('data') ?? []);
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $title = trim((string) ($r['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $out[] = [
                'external_id' => (string) ($r['id'] ?? $r['external_id'] ?? md5($title)),
                'title' => $title,
                'author' => trim((string) ($r['author'] ?? '')),
                'publisher' => trim((string) ($r['publisher'] ?? '')),
                'publish_year' => trim((string) ($r['publish_year'] ?? '')),
                'isbn' => trim((string) ($r['isbn'] ?? '')),
                'source_protocol' => 'z3950',
            ];
        }

        return $out;
    }

    private function extractIsbn(string $identifier): string
    {
        if ($identifier === '') {
            return '';
        }
        preg_match('/((97(8|9))?\d{9}(\d|X))/i', $identifier, $m);
        return isset($m[1]) ? strtoupper((string) $m[1]) : '';
    }
}
