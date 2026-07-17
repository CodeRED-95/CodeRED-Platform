<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetApplicationLocale
{
    public function handle(Request $request, Closure $next)
    {
        App::setLocale(config('app.locale'));

        return $next($request);
    }
}
