<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Str;

class VendorService
{
    public function normalizeName(string $name): string
    {
        return (string) Str::of($name)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }

    public function upsert(array $data, ?Vendor $vendor = null): Vendor
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Nama vendor wajib diisi.');
        }

        $payload = [
            'name' => $name,
            'normalized_name' => $this->normalizeName($name),
            'contact_json' => $data['contact_json'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        if ($vendor) {
            $vendor->fill($payload);
            $vendor->save();
            return $vendor;
        }

        return Vendor::create($payload);
    }
}
