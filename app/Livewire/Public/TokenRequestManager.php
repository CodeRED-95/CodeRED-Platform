<?php

namespace App\Livewire\Public;

use App\Actions\ApiTokenRequests\CreateOtpTokenAction;
use App\Actions\ApiTokenRequests\RevealTokenAction;
use App\Actions\ApiTokenRequests\VerifyOtpTokenAction;
use App\Exceptions\OtpExpiredException;
use App\Exceptions\OtpMaxAttemptsExceededException;
use App\Exceptions\OtpMaxResendsExceededException;
use App\Exceptions\TokenAlreadyRevealedException;
use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\AuditService;
use App\Services\ApiTokens\TokenVaultService;
use Livewire\Attributes\Locked;
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
    #[Locked]
    public ?string $revealedToken = null;
    public bool $confirmingReveal = false;

    // FASE 2: Nuevas propiedades para flujo de OTP
    public string $currentStep = 'status_check'; // status_check, otp_pending, token_ready
    public string $otpCode = '';
    public string $otpStatus = 'pending';
    public int $otpAttemptsRemaining = 5;
    public int $otpResendsRemaining = 3;
    public ?string $otpExpiresAt = null;
    public ?string $otpErrorMessage = null;

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

    // ========================================
    // FASE 2: Nuevos métodos para flujo OTP
    // ========================================

    public function requestOtp(CreateOtpTokenAction $action): void
    {
        if (!$this->foundRequest) {
            $this->otpErrorMessage = 'No se ha encontrado una solicitud.';
            return;
        }

        try {
            $result = $action->execute(
                $this->foundRequest,
                request()->ip(),
                request()->userAgent(),
            );

            $this->currentStep = 'otp_pending';
            $this->otpStatus = 'pending';
            $this->otpAttemptsRemaining = 5;
            $this->otpResendsRemaining = $result['resends_remaining'];
            $this->otpExpiresAt = now()->addMinutes(10)->toIso8601String();
            $this->otpErrorMessage = null;
        } catch (\Exception $e) {
            $this->otpErrorMessage = $e->getMessage();
        }
    }

    public function verifyOtp(VerifyOtpTokenAction $action): void
    {
        if (!$this->foundRequest) {
            $this->otpErrorMessage = 'La solicitud ha expirado.';
            return;
        }

        $this->validate([
            'otpCode' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        try {
            $result = $action->execute(
                $this->foundRequest,
                $this->otpCode,
                request()->ip(),
                request()->userAgent(),
            );

            if ($result['success']) {
                $this->currentStep = 'token_ready';
                $this->otpStatus = 'verified';
                $this->otpErrorMessage = null;
                $this->otpCode = '';
            } else {
                $this->otpAttemptsRemaining = $result['remaining_attempts'] ?? 5;
                $this->otpErrorMessage = $result['message'];
            }
        } catch (OtpExpiredException $e) {
            $this->otpStatus = 'expired';
            $this->otpErrorMessage = $e->getMessage();
        } catch (OtpMaxAttemptsExceededException $e) {
            $this->otpStatus = 'max_attempts';
            $this->otpErrorMessage = $e->getMessage();
        } catch (OtpMaxResendsExceededException $e) {
            $this->otpStatus = 'max_resends';
            $this->otpErrorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->otpErrorMessage = $e->getMessage();
        }
    }

    public function revealToken(): void
    {
        if (!$this->foundRequest) {
            $this->errorMessage = 'No se ha encontrado una solicitud para revelar.';
            return;
        }

        // Si no tiene OTP validado, pedir que lo haga primero
        if (!$this->foundRequest->hasOtpBeenValidated() && $this->currentStep !== 'token_ready') {
            $this->requestOtp(new CreateOtpTokenAction(
                new \App\Services\ApiTokens\OtpService()
            ));
            return;
        }

        $this->confirmingReveal = true;
    }

    public function confirmRevealToken(RevealTokenAction $action): void
    {
        if (!$this->foundRequest) {
            $this->errorMessage = 'La sesión ha expirado, por favor busca tu solicitud de nuevo.';
            $this->confirmingReveal = false;
            return;
        }

        $this->errorMessage = null;
        $this->revealedToken = null;

        try {
            $plainToken = $action->execute(
                $this->foundRequest,
                request()->ip(),
                request()->userAgent(),
            );

            $this->revealedToken = $plainToken;
            $this->foundRequest->refresh();

            // Registrar auditoría
            AuditService::logTokenRevealed(
                $this->foundRequest,
                null,
                request()->ip(),
                request()->userAgent(),
            );

        } catch (TokenAlreadyRevealedException $e) {
            $this->errorMessage = $e->getMessage();
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
