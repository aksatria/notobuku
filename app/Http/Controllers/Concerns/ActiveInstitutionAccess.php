<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

trait ActiveInstitutionAccess
{
    protected function currentInstitutionId(): int
    {
        $user = Auth::user();
        $inst = (int) (
            $user->active_institution_id
            ?? $user->active_inst_id
            ?? $user->institution_id
            ?? 1
        );

        return $inst > 0 ? $inst : 1;
    }
}

