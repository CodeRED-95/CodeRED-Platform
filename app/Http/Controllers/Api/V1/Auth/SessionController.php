<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\ClientSession;
use App\Models\User;
use App\Services\Auth\AuthAuditor;
use App\Services\Auth\ClientSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sesiones activas de la propia cuenta: consultarlas y cerrarlas.
 *
 * Sólo opera sobre las sesiones de quien llama. La revocación de sesiones
 * ajenas es una función administrativa y vive en el panel de Platform.
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly ClientSessionManager $sessions,
        private readonly AuthAuditor $auditor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $current = $request->attributes->get('client_session');
        $currentUuid = $current instanceof ClientSession ? $current->uuid : null;

        $sessions = ClientSession::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn (ClientSession $session): array => [
                'uuid' => $session->uuid,
                'application' => $session->application->value,
                'application_label' => $session->application->label(),
                'device_name' => $session->device_name,
                'platform' => $session->platform,
                'client_version' => $session->client_version,
                'ip_address' => $session->ip_address,
                'created_at' => $session->created_at?->toIso8601String(),
                'last_used_at' => $session->last_used_at?->toIso8601String(),
                'current' => $currentUuid !== null && $session->uuid === $currentUuid,
            ])
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => $sessions]);
    }

    /** Cierra una sesión concreta de la propia cuenta. */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $session = ClientSession::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->where('uuid', $uuid)
            ->first();

        if (! $session instanceof ClientSession) {
            return response()->json(['success' => false, 'message' => 'La sesión no existe.'], 404);
        }

        $this->sessions->revoke($session, $user, 'revoked_by_user');
        $this->auditor->record(AuthAuditor::SESSION_REVOKED, $user, $request, $session);

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Sesión cerrada.'],
        ]);
    }

    /**
     * Cierra todas las sesiones salvo la actual, para que quien lo pide no se
     * expulse a sí mismo sin querer.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $current = $request->attributes->get('client_session');

        $revoked = $this->sessions->revokeAllFor(
            $user,
            null,
            $user,
            'revoked_by_user',
            $current instanceof ClientSession ? $current : null,
        );

        $this->auditor->record(AuthAuditor::SESSION_REVOKED, $user, $request, null, [
            'scope' => 'all_except_current',
            'revoked' => $revoked,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Se cerraron '.$revoked.' sesiones.', 'revoked' => $revoked],
        ]);
    }
}
