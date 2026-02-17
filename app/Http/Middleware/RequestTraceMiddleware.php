<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestTraceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = trim((string) $request->header('X-Trace-Id', ''));
        $traceId = $this->normalizeTraceId($incoming);
        if ($traceId === '') {
            $traceId = bin2hex(random_bytes(16));
        }
        $spanId = bin2hex(random_bytes(8));

        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('span_id', $spanId);
        $request->attributes->set('traceparent', "00-{$traceId}-{$spanId}-01");

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);
        $response->headers->set('traceparent', "00-{$traceId}-{$spanId}-01");

        return $response;
    }

    private function normalizeTraceId(string $value): string
    {
        $value = Str::lower(trim($value));
        if (!preg_match('/^[a-f0-9]{32}$/', $value)) {
            return '';
        }
        if ($value === str_repeat('0', 32)) {
            return '';
        }

        return $value;
    }
}

