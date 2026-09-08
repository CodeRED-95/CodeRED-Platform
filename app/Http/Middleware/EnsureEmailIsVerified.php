<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->requiresEmailVerification() || $this->isAllowedRoute($request)) {
            return $next($request);
        }

        return redirect()->route('email.verify');
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'email.verify',
            'email.verify.submit',
            'email.verify.resend',
            'logout',
            'default.livewire.update',
            'livewire.*',
        );
    }
}
