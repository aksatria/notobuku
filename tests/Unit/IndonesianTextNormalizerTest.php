<?php

namespace Tests\Unit;

use App\Services\Search\IndonesianTextNormalizer;
use Tests\TestCase;

class IndonesianTextNormalizerTest extends TestCase
{
    public function test_normalize_loose_removes_symbols_and_normalizes_space(): void
    {
        $svc = app(IndonesianTextNormalizer::class);

        $normalized = $svc->normalizeLoose('  Buku, Perpustakaan!!! 2024  ');

        $this->assertSame('buku perpustakaan 2024', $normalized);
    }

    public function test_tokenize_includes_stem_tokens_when_enabled(): void
    {
        config(['search.stemming.enabled' => true]);
        $svc = app(IndonesianTextNormalizer::class);

        $tokens = $svc->tokenize('berlarian perpustakaan membaca', true);

        $this->assertContains('berlarian', $tokens);
        $this->assertContains('lari', $tokens);
        $this->assertContains('perpustakaan', $tokens);
        $this->assertContains('pustaka', $tokens);
        $this->assertContains('membaca', $tokens);
        $this->assertContains('baca', $tokens);
    }

    public function test_tokenize_does_not_expand_stem_when_disabled(): void
    {
        config(['search.stemming.enabled' => false]);
        $svc = app(IndonesianTextNormalizer::class);

        $tokens = $svc->tokenize('berlarian perpustakaan', true);

        $this->assertContains('berlarian', $tokens);
        $this->assertContains('perpustakaan', $tokens);
        $this->assertNotContains('lari', $tokens);
        $this->assertNotContains('pustaka', $tokens);
    }
}
