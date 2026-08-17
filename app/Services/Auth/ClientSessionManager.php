<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\ClientApplication;
use App\Exceptions\InvalidRefreshTokenException;
use App\Models\ClientRefreshToken;
use App\Models\ClientSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Ciclo de vida de las sesiones de los clientes oficiales.
 *
 * El access token sigue siendo un PAT de Sanctum, para no duplicar la
 * autenticación que ya usa toda la API, pero se marca con `kind = session` y
 * caduca en minutos. Quien renueva es el refresh token, que se rota en cada uso.
 *
 * Las abilities del token de sesión son un simple marcador: la autorización real
 * la resuelve EnsurePermission contra el RBAC en cada petición. Así, retirar un
 * permiso surte efecto de inmediato y concederlo también, sin esperar a que
 * caduque nada.
 */
final class ClientSessionManager
{
    /**
     * Ability única de los tokens de sesión. No concede nada por sí misma:
     * distingue "esto es una persona" de "esto es una integración".
     */
    public const SESSION_ABILITY = 'session';

    public const KIND_SESSION = 'session';

    public function __construct(private readonly AuthAuditor $auditor) {}

    /**
     * Abre una sesión y emite el primer par de credenciales.
     *
     * @param  array{device_name?:string|null,platform?:string|null,client_version?:string|null}  $device
     */
    public function start(
        User $user,
        ClientApplication $application,
        array $device,
        ?Request $request = null,
    ): IssuedSession {
        return DB::transaction(function () use ($user, $application, $device, $request): IssuedSession {
            $session = ClientSession::create([
                'user_id' => $user->getKey(),
                'application' => $application->value,
                'device_name' => $this->clean($device['device_name'] ?? null, 120),
                'platform' => $this->clean($device['platform'] ?? null, 60),
                'client_version' => $this->clean($device['client_version'] ?? null, 40),
                'ip_address' => $request?->ip(),
                'user_agent' => $this->clean($request?->userAgent(), 255),
                'last_used_at' => now(),
            ]);

            $issued = $this->issueCredentials($user, $session);

            $this->enforceSessionLimit($user, $application, $session);

            $this->auditor->record(AuthAuditor::LOGIN_SUCCESS, $user, $request, $session);

            return $issued;
        });
    }

    /**
     * Canjea un refresh token por credenciales nuevas.
     *
     * @throws InvalidRefreshTokenException
     */
    public function refresh(string $plainRefreshToken, ?Request $request = null): IssuedSession
    {
        $stored = ClientRefreshToken::query()
            ->where('token_hash', ClientRefreshToken::hash($plainRefreshToken))
            ->first();

        if (! $stored instanceof ClientRefreshToken) {
            throw new InvalidRefreshTokenException('La sesión no es válida. Inicia sesión de nuevo.');
        }

        $session = $stored->session;

        if (! $session instanceof ClientSession) {
            throw new InvalidRefreshTokenException('La sesión no es válida. Inicia sesión de nuevo.');
        }

        // Las comprobaciones y sus revocaciones van FUERA de la transacción de
        // emisión. Dentro, la excepción haría rollback y desharía justo aquello
        // que la hace segura: cerrar la sesión ante una reutilización.

        // Reutilización: alguien presenta un refresh ya canjeado. O es una copia
        // robada o el legítimo se quedó atrás; en ambos casos lo seguro es
        // cerrar la sesión entera y obligar a autenticarse de nuevo.
        if ($stored->isUsed()) {
            $this->revoke($session, null, 'refresh_reuse');
            $this->auditor->record(AuthAuditor::REFRESH_REUSE, $session->user, $request, $session);

            throw new InvalidRefreshTokenException('La sesión se cerró por seguridad. Inicia sesión de nuevo.');
        }

        if ($stored->isExpired() || ! $session->isActive()) {
            throw new InvalidRefreshTokenException('La sesión expiró. Inicia sesión de nuevo.');
        }

        $user = $session->user;

        // La cuenta pudo desactivarse mientras la sesión seguía viva.
        if (! $user instanceof User || ! $user->isActive()) {
            $this->revoke($session, null, 'user_inactive');

            throw new InvalidRefreshTokenException('La cuenta no está activa.');
        }

        // El acceso a la aplicación pudo retirarse después del login.
        if (! $this->canAccess($user, $session->application)) {
            $this->revoke($session, null, 'app_access_revoked');

            throw new InvalidRefreshTokenException($session->application->accessDeniedMessage());
        }

        // Consumir el refresh es un compare-and-set atómico: si dos peticiones
        // llegan a la vez con el mismo token, sólo una actualiza la fila y la
        // otra se queda sin canje, en lugar de emitir dos sesiones válidas.
        $consumed = ClientRefreshToken::query()
            ->whereKey($stored->getKey())
            ->whereNull('used_at')
            ->update(['used_at' => now(), 'updated_at' => now()]);

        if ($consumed === 0) {
            throw new InvalidRefreshTokenException('La sesión no es válida. Inicia sesión de nuevo.');
        }

        return DB::transaction(function () use ($user, $session, $stored, $request): IssuedSession {
            $issued = $this->issueCredentials($user, $session);

            $stored->forceFill(['replaced_by_id' => $this->latestRefreshTokenId($session)])->save();

            $session->forceFill([
                'last_used_at' => now(),
                'ip_address' => $request?->ip() ?? $session->ip_address,
            ])->save();

            $this->auditor->record(AuthAuditor::REFRESH, $user, $request, $session);

            return $issued;
        });
    }

    /**
     * Cierra una sesión: su access token y todos sus refresh dejan de servir.
     * No toca las demás sesiones ni ningún token de integración.
     */
    public function revoke(ClientSession $session, ?User $actor = null, string $reason = 'logout'): void
    {
        DB::transaction(function () use ($session, $actor, $reason): void {
            $this->deleteAccessToken($session);

            // Marcar los refresh como usados los inutiliza sin borrar el rastro
            // de la cadena, que es lo que permite investigar una reutilización.
            $session->refreshTokens()->whereNull('used_at')->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);

            if ($session->isActive()) {
                $session->forceFill([
                    'revoked_at' => now(),
                    'revoked_by' => $actor?->getKey(),
                    'revocation_reason' => mb_substr($reason, 0, 80),
                ])->save();
            }
        });
    }

    /**
     * Cierra todas las sesiones de una persona, opcionalmente sólo las de una
     * aplicación o preservando una concreta (la que está pidiendo el cierre).
     */
    public function revokeAllFor(
        User $user,
        ?ClientApplication $application = null,
        ?User $actor = null,
        string $reason = 'revoked',
        ?ClientSession $except = null,
    ): int {
        $query = ClientSession::query()->active()->where('user_id', $user->getKey());

        if ($application !== null) {
            $query->forApplication($application);
        }

        if ($except !== null) {
            $query->whereKeyNot($except->getKey());
        }

        $count = 0;

        foreach ($query->get() as $session) {
            $this->revoke($session, $actor, $reason);
            $count++;
        }

        return $count;
    }

    /** ¿Esta persona puede entrar en esta aplicación ahora mismo? */
    public function canAccess(User $user, ClientApplication $application): bool
    {
        return $user->hasPermission($application->accessPermission());
    }

    /**
     * Sesión asociada al token con el que llega la petición, si lo hay.
     */
    public function resolveFromToken(?PersonalAccessToken $token): ?ClientSession
    {
        if (! $token instanceof PersonalAccessToken || $token->getKey() === null) {
            return null;
        }

        return ClientSession::query()->where('access_token_id', $token->getKey())->first();
    }

    /** Emite access + refresh y los deja apuntados en la sesión. */
    private function issueCredentials(User $user, ClientSession $session): IssuedSession
    {
        // El access token anterior deja de servir en cuanto se emite el nuevo:
        // una rotación no debe dejar dos tokens vivos.
        $this->deleteAccessToken($session);

        $accessExpiresAt = now()->addMinutes(max(1, (int) config('client_sessions.access_token_ttl', 15)));

        $tokenName = sprintf('codered-%s%s',
            $session->application->value,
            $session->device_name !== null ? ' - '.$session->device_name : '',
        );

        $tokenResult = $user->createToken($tokenName, [self::SESSION_ABILITY], $accessExpiresAt);

        $tokenResult->accessToken->forceFill(['kind' => self::KIND_SESSION])->save();

        $refreshPlain = Str::random(80);
        $refreshExpiresAt = now()->addDays(max(1, (int) config('client_sessions.refresh_token_ttl', 30)));

        ClientRefreshToken::create([
            'client_session_id' => $session->getKey(),
            'token_hash' => ClientRefreshToken::hash($refreshPlain),
            'expires_at' => $refreshExpiresAt,
        ]);

        $session->forceFill([
            'access_token_id' => $tokenResult->accessToken->getKey(),
            'last_used_at' => now(),
        ])->save();

        return new IssuedSession(
            $session->refresh(),
            $tokenResult->plainTextToken,
            $accessExpiresAt,
            $refreshPlain,
            $refreshExpiresAt,
        );
    }

    private function deleteAccessToken(ClientSession $session): void
    {
        if ($session->access_token_id === null) {
            return;
        }

        PersonalAccessToken::query()->whereKey($session->access_token_id)->delete();

        $session->forceFill(['access_token_id' => null])->save();
    }

    private function latestRefreshTokenId(ClientSession $session): ?int
    {
        $id = ClientRefreshToken::query()
            ->where('client_session_id', $session->getKey())
            ->whereNull('used_at')
            ->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Mantiene acotado el número de sesiones vivas por aplicación, cerrando las
     * más antiguas. La recién creada nunca es candidata.
     */
    private function enforceSessionLimit(User $user, ClientApplication $application, ClientSession $current): void
    {
        $max = max(1, (int) config('client_sessions.max_sessions_per_application', 5));

        $surplus = ClientSession::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->forApplication($application)
            ->whereKeyNot($current->getKey())
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->skip($max - 1)
            ->take(100)
            ->get();

        foreach ($surplus as $session) {
            $this->revoke($session, null, 'session_limit');
        }
    }

    private function clean(?string $value, int $maxLength): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
