<?php

namespace App\Livewire\Public;

use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\TokenVaultService;
use Livewire\Component;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Models\ApiTokenRequestEvent;

class TokenRequestManager extends Component
{
    public string $tab = 'create';

    // Propiedades para el formulario de creación
    public string $requester_name = '';
    public string $delivery_method = 'whatsapp';
    public string $delivery_destination = '';
    public string $installation_name = '';
    public string $integration_type = '';
    public string $reason = '';
    public bool $terms = false;
    
    // Propiedades para el formulario de consulta
    public string $tracking_code_status = '';
    public string $email_status = '';

    // Propiedades para el resultado
    public ?ApiTokenRequest $foundRequest = null;
    public ?string $errorMessage = null;
    public ?string $revealedToken = null;
    public bool $confirmingReveal = false;

    public function mount(): void
    {
        $this->installation_name = request()->query('installation_name', 'Buscador Shalom Control');
        $this->integration_type = request()->query('integration_type', 'shalom-control-search');
    }

    public function changeTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetStatusFields();
    }

    public function checkStatus(TokenVaultService $vault): void
    {
        $this->validate([
            'tracking_code_status' => ['required', 'string', 'starts_with:CR-'],
            'email_status' => ['required', 'email'],
        ]);

        $this->resetStatusFields();

        $blindIndex = $vault->generateBlindIndex($this->email_status);

        $request = ApiTokenRequest::query()
            ->where('tracking_code', $this->tracking_code_status)
            ->where('requester_email_blind_index', $blindIndex)
            ->first();

        if (!$request) {
            $this->errorMessage = 'No se encontró una solicitud con los datos proporcionados.';
            ApiTokenRequestEvent::logPublicStatusCheck(false, $this->tracking_code_status, 'Request not found');
            return;
        }

        $this->foundRequest = $request;
        ApiTokenRequestEvent::logPublicStatusCheck(true, $this->tracking_code_status, 'Status checked successfully', $request->id);
    }

    public function revealToken(): void
    {
        if (!$this->foundRequest) {
            $this->errorMessage = 'No se ha encontrado una solicitud para revelar.';
            return;
        }
        $this->confirmingReveal = true;
    }
    
    public function confirmRevealToken(TokenVaultService $vault): void
    {
        if (!$this->foundRequest) {
            $this->errorMessage = 'La sesión ha expirado, por favor busca tu solicitud de nuevo.';
            $this->confirmingReveal = false;
            return;
        }

        $this->errorMessage = null;
        $this->revealedToken = null;

        try {
            $revealed = DB::transaction(function () use ($vault) {
                $request = ApiTokenRequest::query()
                    ->whereKey($this->foundRequest->id)
                    ->lockForUpdate()
                    ->first();

                if (!$request) {
                     ApiTokenRequestEvent::logPublicStatusCheck(false, $this->tracking_code_status, 'Request not found in transaction', $this->foundRequest->id);
                    return ['error' => 'No se encontró una solicitud con los datos proporcionados.'];
                }
                
                if ($request->status !== ApiTokenRequestStatus::Approved) {
                    ApiTokenRequestEvent::logPublicStatusCheck(true, $this->tracking_code_status, 'Request not approved', $request->id);
                    return ['error' => 'La solicitud no ha sido aprobada.'];
                }

                if ($request->token_revealed_at) {
                    ApiTokenRequestEvent::logPublicStatusCheck(true, $this->tracking_code_status, 'Token already revealed', $request->id);
                    return ['error' => 'El token ya fue entregado y no puede volver a mostrarse.'];
                }
                
                if (empty($request->token_ciphertext)) {
                    ApiTokenRequestEvent::logPublicStatusCheck(true, $this->tracking_code_status, 'Ciphertext is empty', $request->id);
                    return ['error' => 'Error al procesar la solicitud. Contacte a soporte. [EC:01]'];
                }

                $token = $request->token;
                if (!$token || $token->revoked_at || ($token->expires_at && $token->expires_at->isPast())) {
                    ApiTokenRequestEvent::logPublicStatusCheck(true, $this->tracking_code_status, 'Token revoked or expired', $request->id);
                    return ['error' => 'El token asociado a esta solicitud ha sido revocado o ha expirado.'];
                }

                $plainTextToken = $vault->decrypt($request->token_ciphertext);

                $request->forceFill([
                    'token_revealed_at' => now(),
                    'token_revealed_by_type' => 'public_requester',
                    'delivery_status' => ApiTokenRequestDeliveryStatus::Delivered,
                    'delivered_at' => now(),
                ])->save();

                ApiTokenRequestEvent::logPublicStatusCheck(true, $this->tracking_code_status, 'Token revealed to requester', $request->id);

                return ['token' => $plainTextToken];
            }, 3);

            if (isset($revealed['error'])) {
                $this->errorMessage = $revealed['error'];
            } else {
                $this->revealedToken = $revealed['token'];
                $this->foundRequest->refresh();
            }

        } catch (\Throwable $e) {
            logger()->error('Error revealing token for public request', [
                'request_id' => $this->foundRequest->id, 
                'error' => $e->getMessage()
            ]);
            $this->errorMessage = 'Ocurrió un error inesperado al procesar la solicitud. [EC:02]';
        }

        $this->confirmingReveal = false;
    }


    private function resetStatusFields(): void
    {
        $this->foundRequest = null;
        $this->errorMessage = null;
        $this->revealedToken = null;
        $this->confirmingReveal = false;
    }

    public function render(): View
    {
        return view('livewire.public.token-request-manager');
    }
}
