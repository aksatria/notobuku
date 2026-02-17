<?php

namespace App\Services\Search;

use Illuminate\Support\Str;
use Sastrawi\Stemmer\StemmerFactory;
use Sastrawi\Stemmer\StemmerInterface;

class IndonesianTextNormalizer
{
    private ?StemmerInterface $stemmer = null;

    /** @var array<string, string> */
    private array $stemCache = [];

    public function normalizeLoose(string $text): string
    {
        return Str::of($text)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->toString();
    }

    /**
     * @return string[]
     */
    public function tokenize(string $text, bool $withStem = true): array
    {
        $normalized = $this->normalizeLoose($text);
        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $normalized);
        $tokens = array_values(array_filter(array_map('trim', (array) $tokens), fn ($t) => $t !== ''));

        if (!$withStem || !(bool) config('search.stemming.enabled', true)) {
            return array_values(array_unique($tokens));
        }

        $expanded = [];
        foreach ($tokens as $token) {
            $expanded[] = $token;
            $stem = $this->stemToken($token);
            if ($stem !== '' && $stem !== $token) {
                $expanded[] = $stem;
            }
        }

        return array_values(array_unique($expanded));
    }

    public function stemToken(string $token): string
    {
        $token = trim($this->normalizeLoose($token));
        if ($token === '') {
            return '';
        }
        if (isset($this->stemCache[$token])) {
            return $this->stemCache[$token];
        }
        if (mb_strlen($token) < 3) {
            return $this->stemCache[$token] = $token;
        }

        $stemmed = $token;
        try {
            $stemmer = $this->resolveStemmer();
            if ($stemmer) {
                $stemmed = $this->normalizeLoose((string) $stemmer->stem($token));
                if ($stemmed === '') {
                    $stemmed = $token;
                }
            }
        } catch (\Throwable) {
            $stemmed = $token;
        }

        return $this->stemCache[$token] = $stemmed;
    }

    private function resolveStemmer(): ?StemmerInterface
    {
        if ($this->stemmer !== null) {
            return $this->stemmer;
        }

        try {
            $factory = new StemmerFactory();
            $this->stemmer = $factory->createStemmer();
        } catch (\Throwable) {
            $this->stemmer = null;
        }

        return $this->stemmer;
    }
}
