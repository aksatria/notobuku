<?php

namespace App\Http\Controllers;

use App\Services\AuthorityService;
use Illuminate\Http\Request;

class AuthorityAuthorController extends Controller
{
    public function index(Request $request, AuthorityService $service)
    {
        $q = (string) $request->query('q', '');

        return response()->json($service->searchAuthors($q, 10));
    }
}
