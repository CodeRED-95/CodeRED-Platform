<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\EmailVerificationMail;
use App\Mail\PasswordResetMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionalEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_mail_has_html_plain_text_utf8_and_no_secret(): void
    {
        $mail = new EmailVerificationMail('482731', 'u***@dominio.test');
        $html = $mail->render();
        $text = view('emails.email-verification-text', ['code' => '482731', 'emailMasked' => 'u***@dominio.test', 'expiresInMinutes' => 10])->render();

        $this->assertStringContainsString('482731', $html);
        $this->assertStringContainsString('verificación', $html);
        $this->assertStringContainsString('482731', $text);
        $this->assertStringNotContainsString('RESEND_API_KEY', $html.$text);
    }

    public function test_password_reset_mail_contains_the_link(): void
    {
        $mail = new PasswordResetMail('https://platform.codered.lat/reset-password/token/user@example.com');

        $this->assertStringContainsString('platform.codered.lat/reset-password', $mail->render());
    }
}
