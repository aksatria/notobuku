<?php

namespace App\Http\Controllers;

use App\Services\AuthorityService;
use Illuminate\Http\Request;

class AuthoritySubjectController extends Controller
{
    public function index(Request $request, AuthorityService $service)
    {
        $q = (string) $request->query('q', '');

        return response()->json($service->searchSubjects($q, 10));
    }
}
