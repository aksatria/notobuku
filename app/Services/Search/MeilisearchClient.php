<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

class MeilisearchClient
{
    public function isConfigured(): bool
    {
        $host = trim((string) config('search.meilisearch.host'));
        return $host !== '';
    }

    public function indexName(): string
    {
        return (string) config('search.meilisearch.index', 'notobuku_biblio');
    }

    public function ensureIndex(): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $index = $this->indexName();

        $resp = $this->request('GET', "/indexes/{$index}", null, false);
        if ($resp && $resp->successful()) {
            return;
        }

        $this->request('POST', '/indexes', [
            'uid' => $index,
            'primaryKey' => 'id',
        ], false);
    }

    public function updateSettings(array $settings): void
    {
        $this->ensureIndex();
        $index = $this->indexName();
        $this->request('PATCH', "/indexes/{$index}/settings", $settings, false);
    }

    public function addDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }
        $this->ensureIndex();
        $index = $this->indexName();
        $this->request('POST', "/indexes/{$index}/documents", $documents, false);
    }

    public function deleteDocuments(array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        $this->ensureIndex();
        $index = $this->indexName();
        $this->request('POST', "/indexes/{$index}/documents/delete-batch", $ids, false);
    }

    public function search(string $query, array $payload): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $index = $this->indexName();
        $body = array_merge([
            'q' => $query,
        ], $payload);

        $resp = $this->request('POST', "/indexes/{$index}/search", $body, false);
        if (!$resp || !$resp->successful()) {
            return null;
        }
        return $resp->json();
    }

    private function request(string $method, string $path, ?array $json, bool $throw = true)
    {
        $host = rtrim((string) config('search.meilisearch.host'), '/');
        if ($host === '') {
            return null;
        }

        $timeout = (int) config('search.meilisearch.timeout', 5);
        $key = (string) config('search.meilisearch.key', '');

        $req = Http::timeout($timeout);
        if ($key !== '') {
            $req = $req->withHeaders([
                'X-Meili-API-Key' => $key,
            ]);
        }

        $url = $host . '/' . ltrim($path, '/');
        $method = strtolower($method);

        try {
            if ($json !== null) {
                $resp = $req->withBody(json_encode($json, JSON_UNESCAPED_UNICODE), 'application/json')
                    ->send(Str::upper($method), $url);
            } else {
                $resp = $req->send(Str::upper($method), $url);
            }

            if ($throw && $resp->failed()) {
                $resp->throw();
            }

            return $resp;
        } catch (ConnectionException $e) {
            if ($throw) {
                throw $e;
            }
            return null;
        } catch (\Throwable $e) {
            if ($throw) {
                throw $e;
            }
            return null;
        }
    }
}
