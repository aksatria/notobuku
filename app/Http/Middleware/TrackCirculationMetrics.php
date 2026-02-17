<?php

namespace App\Http\Middleware;

use App\Support\CirculationMetrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackCirculationMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        $routeName = (string) optional($request->route())->getName();
        if ($routeName !== '' && str_starts_with($routeName, 'transaksi.')) {
            $elapsedMs = (microtime(true) - $startedAt) * 1000;
            CirculationMetrics::recordEndpoint($routeName, $elapsedMs, (int) $response->getStatusCode());
        }

        return $response;
    }
}

