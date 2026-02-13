<?php

namespace App\Policies;

use App\Models\User;

class KatalogPolicy
{
    public function import(User $user): bool
    {
        return in_array($user->role ?? 'member', ['super_admin', 'admin', 'staff'], true);
    }

    public function export(User $user): bool
    {
        return in_array($user->role ?? 'member', ['super_admin', 'admin', 'staff'], true);
    }
}
