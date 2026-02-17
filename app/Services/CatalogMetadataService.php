<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Subject;
use App\Services\MarcValidationService;
use App\Services\PustakawanDigital\ExternalApiService;

class CatalogMetadataService
{
    public function lookupByIsbn(string $isbnInput, ExternalApiService $externalApiService): array
    {
        $isbn = preg_replace('/[^0-9Xx]/', '', trim($isbnInput));
        if ($isbn === '' || !in_array(strlen($isbn), [10, 13], true)) {
            return [
                'status' => 422,
                'body' => ['ok' => false, 'message' => 'ISBN tidak valid.'],
            ];
        }

        $book = $externalApiService->getBookByIsbn($isbn);
        if (!$book) {
            return [
                'status' => 404,
                'body' => ['ok' => false, 'message' => 'Data ISBN tidak ditemukan.'],
            ];
        }

        $authors = $book['authors'] ?? [];
        if (is_array($authors)) {
            $authorText = implode(', ', array_slice(array_filter(array_map(function ($a) {
                if (is_array($a)) {
                    return $a['name'] ?? '';
                }
                return (string) $a;
            }, $authors)), 0, 3));
        } else {
            $authorText = (string) $authors;
        }

        $subjects = $book['category'] ?? '';
        $subjectsText = '';
        if (is_array($subjects)) {
            $subjectsText = implode('; ', array_slice(array_filter(array_map('strval', $subjects)), 0, 5));
        } elseif (is_string($subjects)) {
            $subjectsText = $subjects;
        }

        $payload = [
            'title' => (string) ($book['title'] ?? ''),
            'subtitle' => (string) ($book['subtitle'] ?? ''),
            'authors_text' => $authorText,
            'publisher' => (string) ($book['publisher'] ?? ''),
            'publish_year' => (string) ($book['year'] ?? ''),
            'isbn' => (string) ($book['isbn'] ?? $isbn),
            'language' => (string) ($book['language'] ?? 'id'),
            'physical_desc' => !empty($book['page_count']) ? ((int) $book['page_count'] . ' hlm') : '',
            'subjects_text' => $subjectsText,
            'notes' => (string) ($book['description'] ?? ''),
            'cover_url' => (string) ($book['cover_url'] ?? ''),
            'source' => (string) ($book['source'] ?? ''),
        ];

        return [
            'status' => 200,
            'body' => ['ok' => true, 'data' => $payload],
        ];
    }

    public function validateDraft(array $data, int $institutionId, MarcValidationService $validationService): array
    {
        $biblio = new Biblio();
        $biblio->institution_id = $institutionId;
        $biblio->title = trim((string) ($data['title'] ?? ''));
        $biblio->subtitle = trim((string) ($data['subtitle'] ?? ''));
        $biblio->publisher = trim((string) ($data['publisher'] ?? ''));
        $biblio->place_of_publication = trim((string) ($data['place_of_publication'] ?? ''));
        $biblio->publish_year = trim((string) ($data['publish_year'] ?? ''));
        $biblio->language = trim((string) ($data['language'] ?? ''));
        $biblio->ddc = trim((string) ($data['ddc'] ?? ''));
        $biblio->call_number = trim((string) ($data['call_number'] ?? ''));
        $biblio->isbn = trim((string) ($data['isbn'] ?? ''));
        $biblio->issn = trim((string) ($data['issn'] ?? ''));
        $biblio->physical_desc = trim((string) ($data['physical_desc'] ?? ''));
        $biblio->extent = trim((string) ($data['extent'] ?? ''));
        $biblio->material_type = trim((string) ($data['material_type'] ?? ''));
        $biblio->media_type = trim((string) ($data['media_type'] ?? ''));

        $authorsText = trim((string) ($data['authors_text'] ?? ''));
        $subjectsText = trim((string) ($data['subjects_text'] ?? ''));

        $authors = collect();
        if ($authorsText !== '') {
            foreach (preg_split('/[;,]+/', $authorsText) as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $a = new Author(['name' => $name]);
                $a->pivot = (object) ['role' => null];
                $authors->push($a);
            }
        }

        $subjects = collect();
        if ($subjectsText !== '') {
            foreach (preg_split('/[;,]+/', $subjectsText) as $term) {
                $term = trim((string) $term);
                if ($term === '') {
                    continue;
                }
                $subjects->push(new Subject(['term' => $term]));
            }
        }

        $biblio->setRelation('authors', $authors);
        $biblio->setRelation('subjects', $subjects);
        $biblio->setRelation('identifiers', collect());

        $messages = $validationService->validateForExport($biblio);
        $errors = [];
        $warnings = [];
        foreach ($messages as $msg) {
            if (str_starts_with((string) $msg, 'WARN:')) {
                $warnings[] = trim(substr((string) $msg, 5));
            } else {
                $errors[] = (string) $msg;
            }
        }

        return [
            'ok' => true,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}

