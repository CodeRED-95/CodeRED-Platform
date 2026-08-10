<?php

namespace App\Actions\ApiTokenRequests;

use App\Enums\ApiTokenRequestStatus;
use App\Exceptions\TokenAlreadyRevealedException;
use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\AuditService;
use App\Services\ApiTokens\TokenVaultService;
use Illuminate\Support\Facades\DB;

class RevealTokenAction
{
    public function __construct(
        private TokenVaultService $vault,
    ) {}

    /**
     * Revela un token aprobado una sola vez
     *
     * Usa transacción y lockForUpdate para prevenir race conditions
     *
     * @return string Token en plaintext (temporal)
     *
     * @throws TokenAlreadyRevealedException
     * @throws \InvalidArgumentException
     */
    public function execute(
        ApiTokenRequest $request,
        string $ip,
        ?string $userAgent = null,
        $user = null,
    ): string {
        // Verificar status
        if ($request->statusValue() !== ApiTokenRequestStatus::Approved->value) {
            AuditService::logTokenRevealDenied(
                $request,
                $user,
                $ip,
                $userAgent,
                'Request not approved'
            );

            throw new \InvalidArgumentException(
                'La solicitud debe estar aprobada para revelar el token.'
            );
        }

        // Usar transacción con lockForUpdate para evitar race conditions
        $token = DB::transaction(function () use ($request, $ip, $userAgent, $user) {
            // Obtener request con lock
            $lockedRequest = ApiTokenRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verificar que no haya sido revelado
            if ($lockedRequest->hasTokenBeenRevealed()) {
                throw new TokenAlreadyRevealedException();
            }

            // Obtener el token cifrado
            if (!$lockedRequest->token_ciphertext) {
                AuditService::logTokenRevealDenied(
                    $lockedRequest,
                    $user,
                    $ip,
                    $userAgent,
                    'No ciphertext found'
                );

                throw new \RuntimeException('Token no encontrado.');
            }

            // Descifrar token (SOLO EN MEMORIA, no guardar)
            $plainToken = $this->vault->decryptToken($lockedRequest->token_ciphertext);

            // Marcar como revelado
            $lockedRequest->update([
                'token_revealed_at' => now(),
                'token_revealed_by_type' => 'admin',
                'token_revealed_by_user_id' => $user?->id,
            ]);

            // Incrementar contador
            $lockedRequest->incrementTokenRevealCount();

            // Registrar en auditoría
            AuditService::logTokenRevealed(
                $lockedRequest,
                $user,
                $ip,
                $userAgent,
            );

            return $plainToken;
        }, attempts: 3);

        // IMPORTANTE: No guardar el token en ningún lado
        // Solo se retorna para mostrar al usuario en el momento
        return $token;
    }
}
