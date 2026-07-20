<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenOwnerIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $owner = $request->user();
        if ((! $owner instanceof User && ! $owner instanceof ApiClient) || ! $owner->isActive()) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return $next($request);
    }
}
