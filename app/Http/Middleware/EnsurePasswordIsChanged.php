<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password || $this->isAllowedRoute($request)) {
            return $next($request);
        }

        return redirect()->route('account.change-password');
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'account.change-password',
            'logout',
            'default.livewire.update',
            'livewire.*'
        );
    }
}
