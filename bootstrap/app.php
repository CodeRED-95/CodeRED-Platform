<?php

use App\Http\Middleware\EnsureApiTokenNotExpired;
use App\Http\Middleware\EnsureApiTokenOwnerIsActive;
use App\Http\Middleware\EnsureApiVersion;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsurePrivateApiCaching;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetApplicationLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->alias([
            'api.token.expiration' => EnsureApiTokenNotExpired::class,
            'api.token-owner-active' => EnsureApiTokenOwnerIsActive::class,
            'api.private-cache' => EnsurePrivateApiCaching::class,
            'abilities' => CheckAbilities::class,
        ]);

        $middleware->web(append: [
            SetApplicationLocale::class,
            EnsureUserIsActive::class,
            EnsurePasswordIsChanged::class,
        ]);

        $middleware->api(
            prepend: [EnsureApiTokenNotExpired::class],
            append: [EnsureApiVersion::class],
        );

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*');

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'No autenticado.'], 401)
            : null);
        $exceptions->render(fn (MissingAbilityException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'El token no tiene permiso para realizar esta acción.'], 403)
            : null);
        $exceptions->render(fn (AuthorizationException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'El token no tiene permiso para realizar esta acción.'], 403)
            : null);
        $exceptions->render(fn (AccessDeniedHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'El token no tiene permiso para realizar esta acción.'], 403)
            : null);
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Agencia no encontrada.'], 404)
            : null);
        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Agencia no encontrada.'], 404)
            : null);
        $exceptions->render(fn (ValidationException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Los datos proporcionados no son válidos.', 'errors' => $e->errors()], 422)
            : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Se superó el límite de solicitudes.'], 429, $e->getHeaders())
            : null);
        $exceptions->render(fn (Throwable $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Ocurrió un error inesperado.'], 500)
            : null);
    })->create();
