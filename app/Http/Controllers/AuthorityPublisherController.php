<?php

namespace App\Http\Controllers;

use App\Services\AuthorityService;
use Illuminate\Http\Request;

class AuthorityPublisherController extends Controller
{
    public function index(Request $request, AuthorityService $service)
    {
        $q = (string) $request->query('q', '');

        return response()->json($service->searchPublishers($q, 10));
    }
}
