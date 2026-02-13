<?php

namespace App\Http\Controllers;

use App\Services\DdcService;
use Illuminate\Http\Request;

class DdcController extends Controller
{
    public function search(Request $request, DdcService $service)
    {
        $q = (string) $request->query('q', '');

        return response()->json($service->search($q, 10));
    }
}
