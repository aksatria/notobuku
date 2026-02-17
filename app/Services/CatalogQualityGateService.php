<?php

namespace App\Services;

use App\Models\Biblio;

class CatalogQualityGateService
{
    public function evaluate(array $data, int $institutionId, ?int $ignoreBiblioId = null): array
    {
        $errors = [];
        $warnings = [];

        $title = trim((string) ($data['title'] ?? ''));
        $authors = trim((string) ($data['authors_text'] ?? ''));
        $subjects = trim((string) ($data['subjects_text'] ?? ''));
        $isbn = trim((string) ($data['isbn'] ?? ''));
        $ddc = trim((string) ($data['ddc'] ?? ''));
        $callNumber = trim((string) ($data['call_number'] ?? ''));
        $year = (int) ($data['publish_year'] ?? 0);

        if ($title === '') {
            $errors[] = 'Judul wajib diisi.';
        }
        if ($authors === '') {
            $errors[] = 'Pengarang wajib diisi.';
        }
        if ($subjects === '') {
            $errors[] = 'Subjek minimal 1 wajib diisi.';
        }
        if ($isbn === '') {
            $errors[] = 'ISBN wajib diisi sebelum publish.';
        }
        if ($ddc === '') {
            $errors[] = 'DDC wajib diisi sebelum publish.';
        }
        $currentYear = (int) now()->format('Y');
        if ($year > ($currentYear + 1)) {
            $errors[] = 'Tahun terbit melebihi tahun berjalan.';
        }
        if ($ddc !== '' && !$this->isValidDdc($ddc)) {
            $errors[] = 'Format DDC tidak valid. Gunakan pola numerik (contoh: 297.07).';
        }
        if ($isbn !== '' && !$this->isValidIsbn($isbn)) {
            $errors[] = 'Format ISBN tidak valid (harus ISBN-10 atau ISBN-13).';
        }

        if ($isbn !== '') {
            $isbnDup = Biblio::query()
                ->where('institution_id', $institutionId)
                ->where('isbn', $isbn)
                ->when($ignoreBiblioId, fn ($q) => $q->where('id', '!=', $ignoreBiblioId))
                ->first(['id', 'title']);
            if ($isbnDup) {
                $warnings[] = 'ISBN sudah dipakai judul lain: #' . $isbnDup->id . ' ' . (string) $isbnDup->title;
            }
        }

        if ($title !== '') {
            $normalizedTitle = $this->normalizeLoose($title . ' ' . (string) ($data['subtitle'] ?? ''));
            $titleDup = Biblio::query()
                ->where('institution_id', $institutionId)
                ->where('normalized_title', $normalizedTitle)
                ->when($ignoreBiblioId, fn ($q) => $q->where('id', '!=', $ignoreBiblioId))
                ->first(['id', 'title', 'publish_year']);
            if ($titleDup) {
                $warnings[] = 'Kemungkinan duplikat judul: #' . $titleDup->id . ' ' . (string) $titleDup->title;
            }

            if ($isbn !== '') {
                $titleIsbnDup = Biblio::query()
                    ->where('institution_id', $institutionId)
                    ->where('isbn', $isbn)
                    ->where('normalized_title', $normalizedTitle)
                    ->when($ignoreBiblioId, fn ($q) => $q->where('id', '!=', $ignoreBiblioId))
                    ->first(['id', 'title']);
                if ($titleIsbnDup) {
                    $warnings[] = 'Duplikasi kuat judul+ISBN terdeteksi: #' . $titleIsbnDup->id . ' ' . (string) $titleIsbnDup->title;
                }
            }
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function normalizeLoose(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        return trim($text);
    }

    private function isValidDdc(string $ddc): bool
    {
        $ddc = trim($ddc);
        if ($ddc === '') {
            return false;
        }

        return (bool) preg_match('/^\d{3}(\.\d{1,6})?$/', $ddc);
    }

    private function isValidIsbn(string $isbn): bool
    {
        $clean = strtoupper((string) preg_replace('/[^0-9X]/i', '', $isbn));
        $len = strlen($clean);
        if ($len !== 10 && $len !== 13) {
            return false;
        }

        if ($len === 13) {
            return ctype_digit($clean);
        }

        return (bool) preg_match('/^\d{9}[0-9X]$/', $clean);
    }
}
