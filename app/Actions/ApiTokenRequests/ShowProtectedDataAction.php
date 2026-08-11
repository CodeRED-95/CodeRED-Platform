<?php

namespace App\Actions\ApiTokenRequests;

use App\Models\ApiTokenRequest;
use App\Models\User;
use App\Services\ApiTokens\AuditService;
use App\Services\ApiTokens\TokenVaultService;
use Illuminate\Auth\Access\AuthorizationException;

class ShowProtectedDataAction
{
    public function __construct(
        private TokenVaultService $vault,
    ) {}

    /**
     * Muestra datos protegidos (cifrados) de una solicitud
     *
     * Solo administradores con permiso pueden ver esto
     * Los datos se descifran SOLO EN MEMORIA, nunca se guardan plaintext
     *
     * @return array{
     *     requester_name: string,
     *     requester_phone: string,
     *     purpose: string,
     *     delivery_method: string,
     *     delivery_reason: ?string,
     * }
     *
     * @throws AuthorizationException
     */
    public function execute(
        ApiTokenRequest $request,
        User $user,
        string $ip,
        ?string $userAgent = null,
    ): array {
        // Verificar permiso
        if (! $user->hasPermission('api-token-requests.view-protected-data')) {
            AuditService::logProtectedDataViewDenied(
                $request,
                $user,
                $ip,
                $userAgent,
                'Permission denied'
            );

            throw new AuthorizationException(
                'No tienes permiso para ver datos protegidos.'
            );
        }

        // Descifrar datos (SOLO EN MEMORIA)
        $protectedData = [
            'requester_name' => $request->requester_name,
            'requester_phone' => $request->requester_phone,
            'purpose' => $request->purpose,
            'delivery_method' => $request->delivery_method,
            'delivery_reason' => $request->delivery_reason,
        ];

        // Registrar visualización en auditoría
        AuditService::logProtectedDataView($request, $user, $ip, $userAgent);

        // Incrementar contador
        $request->incrementProtectedDataViewCount($ip);

        // IMPORTANTE: No retornar con referencias
        // Los datos retornados se mostrarán solo en la UI
        // y nunca se guardarán o logearán en plaintext
        return $protectedData;
    }
}
