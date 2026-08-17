<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class OtpValidation extends Model
{
    /**
     * Codigo en claro, disponible solo en la peticion que lo genero.
     *
     * No es un atributo del modelo ni se persiste: la columna guarda el hash.
     * Existe para que el correo pueda mostrarlo sin volver a generarlo, y se
     * pierde en cuanto termina la peticion.
     */
    private ?string $plainCode = null;

    public function setPlainCode(string $code): void
    {
        $this->plainCode = $code;
    }

    public function plainCode(): ?string
    {
        return $this->plainCode;
    }

    protected $fillable = [
        'api_token_request_id',
        'email_blind_index',
        'code_hash',
        'expires_at',
        'validated_at',
        'attempts_count',
        'max_attempts',
        'resends_count',
        'max_resends',
        'requested_ip',
        'requested_user_agent',
        'last_attempt_at',
        'last_attempt_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'validated_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'attempts_count' => 'integer',
            'max_attempts' => 'integer',
            'resends_count' => 'integer',
            'max_resends' => 'integer',
        ];
    }

    public function apiTokenRequest(): BelongsTo
    {
        return $this->belongsTo(ApiTokenRequest::class);
    }

    /**
     * Verifica si el código OTP es válido
     */
    public function verifyCode(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }

    /**
     * Verifica si el OTP ha expirado
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Verifica si ha llegado al máximo de intentos
     */
    public function hasMaxAttemptsReached(): bool
    {
        return $this->attempts_count >= $this->max_attempts;
    }

    /**
     * Verifica si ha llegado al máximo de reenvíos
     */
    public function hasMaxResendsReached(): bool
    {
        return $this->resends_count >= $this->max_resends;
    }

    /**
     * Verifica si ya fue validado
     */
    public function isValidated(): bool
    {
        return $this->validated_at !== null;
    }

    /**
     * Incrementa el contador de intentos
     */
    public function recordAttempt(string $ip, ?string $userAgent = null): void
    {
        $this->update([
            'attempts_count' => $this->attempts_count + 1,
            'last_attempt_at' => now(),
            'last_attempt_ip' => $ip,
        ]);
    }

    /**
     * Incrementa el contador de reenvíos
     */
    public function recordResend(string $ip, ?string $userAgent = null): void
    {
        $this->update([
            'resends_count' => $this->resends_count + 1,
            'requested_ip' => $ip,
            'requested_user_agent' => $userAgent,
        ]);
    }

    /**
     * Marca como validado
     */
    public function markAsValidated(): void
    {
        $this->update(['validated_at' => now()]);
    }

    /**
     * Obtiene el tiempo restante en minutos
     */
    public function getRemainingMinutes(): int
    {
        if (! $this->expires_at) {
            return 0;
        }

        $remaining = now()->diffInMinutes($this->expires_at, false);

        return max(0, $remaining);
    }

    /**
     * Obtiene intentos restantes
     */
    public function getRemainingAttempts(): int
    {
        return max(0, $this->max_attempts - $this->attempts_count);
    }

    /**
     * Obtiene reenvíos restantes
     */
    public function getRemainingResends(): int
    {
        return max(0, $this->max_resends - $this->resends_count);
    }

    /**
     * Verifica si puede reenviar
     */
    public function canResend(): bool
    {
        return ! $this->hasMaxResendsReached();
    }

    /**
     * Verifica si puede verificar
     */
    public function canVerify(): bool
    {
        return ! $this->isExpired() && ! $this->hasMaxAttemptsReached() && ! $this->isValidated();
    }
}
