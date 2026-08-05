<?php

namespace App\Enums;

enum TokenRequestAuditAction: string
{
    // OTP actions
    case OtpRequested = 'otp_requested';
    case OtpVerified = 'otp_verified';
    case OtpResent = 'otp_resent';
    case OtpExpired = 'otp_expired';
    case OtpMaxAttemptsReached = 'otp_max_attempts_reached';
    case OtpMaxResendsReached = 'otp_max_resends_reached';

    // Protected data actions
    case ProtectedDataViewed = 'protected_data_viewed';
    case ProtectedDataViewDenied = 'protected_data_view_denied';

    // Token reveal actions
    case TokenRevealed = 'token_revealed';
    case TokenCopied = 'token_copied';
    case TokenRevealDenied = 'token_reveal_denied';
    case TokenAlreadyRevealed = 'token_already_revealed';

    // Delivery actions
    case DeliveryConfirmed = 'delivery_confirmed';
    case DeliveryDenied = 'delivery_denied';

    // Administrative actions
    case ApprovalCancelled = 'approval_cancelled';
    case TokenRegenerated = 'token_regenerated';

    // Request status changes
    case RequestApproved = 'request_approved';
    case RequestRejected = 'request_rejected';
    case RequestCancelled = 'request_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OtpRequested => 'Código OTP solicitado',
            self::OtpVerified => 'Código OTP verificado',
            self::OtpResent => 'Código OTP reenviado',
            self::OtpExpired => 'Código OTP expirado',
            self::OtpMaxAttemptsReached => 'Máximo de intentos OTP alcanzado',
            self::OtpMaxResendsReached => 'Máximo de reenvíos OTP alcanzado',
            self::ProtectedDataViewed => 'Datos protegidos visualizados',
            self::ProtectedDataViewDenied => 'Visualización de datos protegidos denegada',
            self::TokenRevealed => 'Token revelado',
            self::TokenCopied => 'Token copiado',
            self::TokenRevealDenied => 'Revelación de token denegada',
            self::TokenAlreadyRevealed => 'Token ya había sido revelado',
            self::DeliveryConfirmed => 'Entrega confirmada',
            self::DeliveryDenied => 'Entrega denegada',
            self::ApprovalCancelled => 'Aprobación cancelada',
            self::TokenRegenerated => 'Token regenerado',
            self::RequestApproved => 'Solicitud aprobada',
            self::RequestRejected => 'Solicitud rechazada',
            self::RequestCancelled => 'Solicitud cancelada',
        };
    }

    public function isSensitive(): bool
    {
        return in_array($this, [
            self::ProtectedDataViewed,
            self::TokenRevealed,
            self::TokenCopied,
        ], true);
    }
}
