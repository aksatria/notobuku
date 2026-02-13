<?php

namespace Tests\Unit;

use App\Services\VendorService;
use PHPUnit\Framework\TestCase;

class VendorServiceTest extends TestCase
{
    public function test_normalize_name(): void
    {
        $svc = new VendorService();

        $name = '  PT. Gramedia, Tbk! ';
        $normalized = $svc->normalizeName($name);

        $this->assertSame('pt gramedia tbk', $normalized);
    }
}
