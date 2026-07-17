<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiVersion
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('api/v1/*')) {
            return response()->json([
                'message' => __('Versión de API no soportada.'),
            ], 404);
        }

        return $next($request);
    }
}
