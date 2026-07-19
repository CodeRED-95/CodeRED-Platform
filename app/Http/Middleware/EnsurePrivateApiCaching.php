<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivateApiCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $cacheControl = (string) $response->headers->get('Cache-Control');
        if (! str_contains($cacheControl, 'no-store')) {
            $response->headers->set('Cache-Control', 'private, must-revalidate');
        }
        $response->setVary(['Authorization', 'Accept-Encoding'], false);

        return $response;
    }
}
