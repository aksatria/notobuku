<?php

namespace App\Http\Controllers\Concerns;

trait CatalogAccess
{
    protected function currentInstitutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    protected function canManageCatalog(): bool
    {
        $role = (string) (auth()->user()->role ?? 'member');
        return in_array($role, ['super_admin', 'admin', 'staff'], true);
    }
}

