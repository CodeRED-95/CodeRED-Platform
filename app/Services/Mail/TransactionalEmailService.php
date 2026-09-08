<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Mail\EmailVerificationMail;
use App\Mail\PasswordResetMail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Log;
use Resend\Laravel\Facades\Resend;
use Throwable;

final class TransactionalEmailService
{
    public function send(EmailLog $log, array $data): void
    {
        if (in_array($log->status, [EmailLog::SENT, EmailLog::DELIVERED, EmailLog::BOUNCED, EmailLog::COMPLAINED], true)) {
            return;
        }

        $mailable = match ($log->type) {
            EmailLog::TYPE_EMAIL_VERIFICATION => new EmailVerificationMail(
                (string) ($data['code'] ?? ''),
                (string) ($data['email_masked'] ?? '***@***'),
            ),
            EmailLog::TYPE_PASSWORD_RESET => new PasswordResetMail((string) ($data['url'] ?? '')),
            default => throw new \InvalidArgumentException('Tipo de correo transaccional no soportado.'),
        };

        $message = [
            'from' => sprintf('%s <%s>', config('mail.from.name'), config('mail.from.address')),
            'to' => [$log->recipient],
            'subject' => $mailable->envelope()->subject,
            'html' => $mailable->render(),
            'text' => view($mailable->content()->text, $mailable->content()->with)->render(),
        ];

        try {
            $response = Resend::emails()->send($message);
            $providerId = data_get($response, 'id') ?? data_get($response, 'data.id');

            $log->forceFill([
                'status' => EmailLog::SENT,
                'provider_message_id' => is_string($providerId) ? $providerId : null,
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => EmailLog::FAILED,
                'failed_at' => now(),
                'failure_category' => $this->failureCategory($exception),
            ])->save();

            Log::error('transactional_email_failed', [
                'email_log_id' => $log->getKey(),
                'type' => $log->type,
                'recipient_domain' => substr(strrchr($log->recipient, '@') ?: '', 1),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function failureCategory(Throwable $exception): string
    {
        return match (true) {
            str_contains(strtolower($exception->getMessage()), 'timeout') => 'timeout',
            str_contains(strtolower($exception->getMessage()), 'rate') => 'rate_limited',
            default => 'provider_error',
        };
    }
}
