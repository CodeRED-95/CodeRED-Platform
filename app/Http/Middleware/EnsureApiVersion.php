<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiVersion
{
    public function handle(Request $request, Closure $next)
    {
        if (! (bool) config('api.enabled')) {
            return response()->json(['message' => 'La API no está disponible.'], 503);
        }

        if (! $request->is('api/v1/*')) {
            return response()->json([
                'message' => __('Versión de API no soportada.'),
            ], 404);
        }

        return $next($request);
    }
}
