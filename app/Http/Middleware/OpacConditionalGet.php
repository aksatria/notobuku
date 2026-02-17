<?php

namespace App\Http\Middleware;

use App\Models\Biblio;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class OpacConditionalGet
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);
        $lastModified = $this->resolveLastModified($request, $institutionId);
        $etag = $this->resolveEtag($request, $lastModified);

        $response->setPublic();
        $response->setEtag($etag);
        if ($lastModified !== null) {
            $response->setLastModified($lastModified);
        }
        $response->headers->set('Cache-Control', 'public, max-age=120, stale-while-revalidate=60');
        $response->headers->set('Vary', 'Accept, Accept-Encoding');
        if ($request->routeIs('opac.suggest')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'public, max-age=30, stale-while-revalidate=30');
        }

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    private function resolveLastModified(Request $request, int $institutionId): ?\DateTimeInterface
    {
        if ($request->routeIs('opac.show')) {
            $id = (int) $request->route('id');
            $raw = Biblio::query()
                ->where('institution_id', $institutionId)
                ->where('id', $id)
                ->value('updated_at');
            return $raw ? Carbon::parse($raw) : null;
        }

        if ($request->routeIs('opac.index') || $request->routeIs('opac.suggest')) {
            $raw = Biblio::query()
                ->where('institution_id', $institutionId)
                ->max('updated_at');
            return $raw ? Carbon::parse($raw) : null;
        }

        return null;
    }

    private function resolveEtag(Request $request, ?\DateTimeInterface $lastModified): string
    {
        $seed = implode('|', [
            'opac',
            $request->route()?->getName() ?? 'unknown',
            $request->fullUrl(),
            $lastModified ? $lastModified->getTimestamp() : '0',
        ]);

        return '"' . sha1($seed) . '"';
    }
}
