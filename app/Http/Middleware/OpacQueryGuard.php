<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OpacQueryGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) config('notobuku.opac.query_guard.enabled', true);
        if (!$enabled) {
            return $next($request);
        }

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return $next($request);
        }

        $violation = $this->detectViolation($query);
        if ($violation === null) {
            return $next($request);
        }

        $payload = [
            'ok' => false,
            'error' => 'query_rejected',
            'reason' => $violation,
        ];

        if ($request->expectsJson() || $request->ajax() || $request->routeIs('opac.suggest')) {
            return response()->json($payload, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response(
            'Query ditolak: pola query tidak aman atau terlalu berat (' . $violation . ').',
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    private function detectViolation(string $query): ?string
    {
        $maxLen = max(16, (int) config('notobuku.opac.query_guard.max_query_length', 120));
        if (mb_strlen($query) > $maxLen) {
            return 'query_too_long';
        }

        $tokens = preg_split('/\s+/', trim($query));
        $tokenCount = count(array_values(array_filter((array) $tokens, fn ($t) => trim((string) $t) !== '')));
        $maxTokens = max(3, (int) config('notobuku.opac.query_guard.max_tokens', 12));
        if ($tokenCount > $maxTokens) {
            return 'too_many_tokens';
        }

        if ((bool) config('notobuku.opac.query_guard.block_wildcards', true)) {
            // Hindari wildcard abuse yang memicu full scan berat.
            if (preg_match('/[%_*?]{2,}/', $query) || preg_match('/(^|\\s)[%_*?]+($|\\s)/', $query)) {
                return 'wildcard_pattern';
            }
        }

        if ((bool) config('notobuku.opac.query_guard.block_sqli_like', true)) {
            $lower = mb_strtolower($query);
            if (preg_match('/\\b(or|and)\\b\\s+\\d+\\s*=\\s*\\d+/', $lower)) {
                return 'suspicious_boolean_expression';
            }
            if (preg_match('/(--|\\/\\*|\\*\\/|;\\s*(drop|truncate|delete|update|insert)\\b)/i', $query)) {
                return 'suspicious_operator';
            }
        }

        return null;
    }
}
