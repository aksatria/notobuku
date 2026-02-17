<?php

namespace App\Http\Middleware;

use App\Support\OpacMetrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackOpacMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (microtime(true) - $start) * 1000;
        $endpoint = (string) ($request->route()?->getName() ?: $request->path());
        $status = (int) $response->getStatusCode();
        $traceId = (string) ($request->attributes->get('trace_id') ?: $request->header('X-Trace-Id', ''));
        OpacMetrics::recordRequest($endpoint, $status, $durationMs, $traceId);

        return $response;
    }
}
