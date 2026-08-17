<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\ActivityLog;
use App\Models\ClientSession;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Registro de los eventos de autenticación en la auditoría existente.
 *
 * Nunca recibe ni escribe secretos: ni contraseñas, ni access tokens, ni refresh
 * tokens, ni tokens de API. Lo que identifica a una sesión en el registro es su
 * UUID público, que no sirve para autenticarse.
 */
final class AuthAuditor
{
    public const LOGIN_SUCCESS = 'auth.login.success';
    public const LOGIN_FAILED = 'auth.login.failed';
    public const LOGIN_DENIED = 'auth.login.denied';
    public const REFRESH = 'auth.refresh';
    public const REFRESH_REUSE = 'auth.refresh.reuse_detected';
    public const LOGOUT = 'auth.logout';
    public const SESSION_REVOKED = 'auth.session.revoked';
    public const PASSWORD_CHANGED = 'auth.password.changed';

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $action,
        ?User $user,
        ?Request $request = null,
        ?ClientSession $session = null,
        array $context = [],
    ): void {
        ActivityLog::create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'auditable_type' => $session !== null ? ClientSession::class : null,
            'auditable_id' => $session?->getKey(),
            'old_values' => null,
            'new_values' => $this->payload($session, $context),
            'changed_fields' => null,
            'ip_address' => $request?->ip(),
            // El user agent es texto ajeno: se recorta para no desbordar la columna.
            'user_agent' => $this->truncate($request?->userAgent()),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function payload(?ClientSession $session, array $context): array
    {
        if ($session === null) {
            return $context;
        }

        return array_merge([
            'session_uuid' => $session->uuid,
            'application' => $session->application->value,
            'device_name' => $session->device_name,
        ], $context);
    }

    private function truncate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, 255);
    }
}
