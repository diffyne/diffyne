<?php

namespace Diffyne\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OptimizeDiffyneResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only optimize Diffyne JSON responses
        if (! $this->isDiffyneRequest($request) || ! $response->headers->get('Content-Type') === 'application/json') {
            return $response;
        }

        // Add cache headers for Diffyne endpoints
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        // Enable compression if supported and configured
        if (config('diffyne.performance.enable_compression', true) && $this->supportsCompression($request)) {
            $content = $response->getContent();

            if ($content && strlen($content) > 1024) { // Only compress if > 1KB
                $compressed = gzencode($content, 6); // Level 6 = good balance

                if ($compressed && strlen($compressed) < strlen($content)) {
                    $response->setContent($compressed);
                    $response->headers->set('Content-Encoding', 'gzip');
                    $response->headers->set('Content-Length', strlen($compressed));
                    $response->headers->set('Vary', 'Accept-Encoding');
                }
            }
        }

        return $response;
    }

    /**
     * Check if this is a Diffyne request.
     */
    protected function isDiffyneRequest(Request $request): bool
    {
        return str_starts_with($request->path(), '_diffyne');
    }

    /**
     * Check if client supports gzip compression.
     */
    protected function supportsCompression(Request $request): bool
    {
        $acceptEncoding = $request->header('Accept-Encoding', '');

        return str_contains($acceptEncoding, 'gzip');
    }
}
