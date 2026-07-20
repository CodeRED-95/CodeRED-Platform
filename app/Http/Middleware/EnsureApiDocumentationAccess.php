<?php

namespace App\Http\Middleware;

use App\Services\ApiDocumentationSettingsService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiDocumentationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('api.docs_enabled'), 404);

        if (! app(ApiDocumentationSettingsService::class)->isPublic() && ! auth()->check()) {
            return new RedirectResponse(route('login'));
        }

        return $next($request);
    }
}
