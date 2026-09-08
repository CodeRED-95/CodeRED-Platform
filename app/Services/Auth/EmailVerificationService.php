<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\EmailVerificationAttemptsExceededException;
use App\Exceptions\EmailVerificationCooldownException;
use App\Jobs\SendTransactionalEmail;
use App\Models\EmailLog;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class EmailVerificationService
{
    public const CODE_LENGTH = 6;
    public const EXPIRES_MINUTES = 10;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    /**
     * Sends a new code, or reuses the current valid code for login retries.
     * The clear code only exists in the queued job payload for this delivery.
     * It is never persisted in a model, log, response or exception.
     *
     * @return array{expires_in:int,resend_available_in:int,resent:bool}
     */
    public function ensureCode(User $user, ?Request $request = null, bool $forceNew = false): array
    {
        $ip = $request?->ip();
        $userAgent = $request?->userAgent();
        $now = now();

        $result = DB::transaction(function () use ($user, $ip, $userAgent, $now, $forceNew): array {
            $latest = EmailVerificationCode::query()
                ->where('user_id', $user->getKey())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $forceNew && $latest instanceof EmailVerificationCode && ! $latest->isUsed() && ! $latest->isExpired()) {
                return [
                    'code' => null,
                    'expires_at' => $latest->expires_at,
                    'last_sent_at' => $latest->last_sent_at,
                    'resent' => false,
                ];
            }

            if ($latest instanceof EmailVerificationCode
                && $latest->last_sent_at !== null
                && $latest->last_sent_at->diffInSeconds($now) < self::RESEND_COOLDOWN_SECONDS) {
                throw new EmailVerificationCooldownException(
                    self::RESEND_COOLDOWN_SECONDS - $latest->last_sent_at->diffInSeconds($now)
                );
            }

            EmailVerificationCode::query()
                ->where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->update(['used_at' => $now]);

            $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
            $verification = EmailVerificationCode::query()->create([
                'user_id' => $user->getKey(),
                'email' => (string) $user->email,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'max_attempts' => self::MAX_ATTEMPTS,
                'expires_at' => $now->copy()->addMinutes(self::EXPIRES_MINUTES),
                'last_sent_at' => $now,
                'requested_ip' => $ip,
            ]);

            $log = EmailLog::query()->create([
                'user_id' => $user->getKey(),
                'recipient' => (string) $user->email,
                'type' => EmailLog::TYPE_EMAIL_VERIFICATION,
                'provider' => 'resend',
                'status' => EmailLog::QUEUED,
                'queued_at' => $now,
                'metadata' => ['verification_code_id' => $verification->getKey()],
            ]);

            SendTransactionalEmail::dispatch(
                $log->getKey(),
                EmailLog::TYPE_EMAIL_VERIFICATION,
                (string) $user->email,
                ['code' => $code, 'email_masked' => self::maskEmail((string) $user->email)],
            )->afterCommit();

            return [
                'code' => $code,
                'expires_at' => $verification->expires_at,
                'last_sent_at' => $verification->last_sent_at,
                'resent' => true,
            ];
        });

        return [
            'expires_in' => max(0, now()->diffInSeconds($result['expires_at'], false)),
            'resend_available_in' => max(0, self::RESEND_COOLDOWN_SECONDS - now()->diffInSeconds($result['last_sent_at'])),
            'resent' => $result['resent'],
        ];
    }

    /**
     * @return array{status:string,remaining_attempts:int}
     */
    public function verify(User $user, string $code, ?Request $request = null): array
    {
        $code = trim($code);

        return DB::transaction(function () use ($user, $code, $request): array {
            $verification = EmailVerificationCode::query()
                ->where('user_id', $user->getKey())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $verification instanceof EmailVerificationCode || $verification->isUsed() || $verification->isExpired()) {
                return ['status' => 'expired', 'remaining_attempts' => 0];
            }

            if ($verification->attempts >= $verification->max_attempts) {
                throw new EmailVerificationAttemptsExceededException;
            }

            $verification->increment('attempts');
            $remaining = max(0, $verification->max_attempts - $verification->attempts);

            if (! Hash::check($code, $verification->code_hash)) {
                return ['status' => 'invalid', 'remaining_attempts' => $remaining];
            }

            $verification->forceFill([
                'used_at' => now(),
                'verified_ip' => $request?->ip(),
            ])->save();
            $user->forceFill(['email_verified_at' => now()])->save();

            return ['status' => 'verified', 'remaining_attempts' => $remaining];
        });
    }

    public function status(User $user): array
    {
        $latest = EmailVerificationCode::query()->where('user_id', $user->getKey())->latest('id')->first();

        if (! $latest instanceof EmailVerificationCode || $latest->isUsed()) {
            return ['expires_in' => 0, 'resend_available_in' => 0];
        }

        return [
            'expires_in' => max(0, now()->diffInSeconds($latest->expires_at, false)),
            'resend_available_in' => max(0, self::RESEND_COOLDOWN_SECONDS - now()->diffInSeconds($latest->last_sent_at)),
        ];
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return $local !== '' && $domain !== ''
            ? mb_substr($local, 0, 1).'***@'.$domain
            : '***@***';
    }
}
