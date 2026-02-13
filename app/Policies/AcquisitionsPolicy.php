<?php

namespace App\Policies;

use App\Models\User;

class AcquisitionsPolicy
{
    private function isStaff(User $user): bool
    {
        return in_array($user->role ?? 'member', ['super_admin', 'admin', 'staff'], true);
    }

    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, $model = null): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(User $user, $model = null): bool
    {
        return $this->isStaff($user);
    }

    public function approve(User $user, $model = null): bool
    {
        return $this->isStaff($user);
    }

    public function reject(User $user, $model = null): bool
    {
        return $this->isStaff($user);
    }

    public function receive(User $user, $model = null): bool
    {
        return $this->isStaff($user);
    }
}
