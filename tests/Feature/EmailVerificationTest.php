<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendTransactionalEmail;
use App\Models\EmailLog;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_pending_account_and_queues_one_hashed_code(): void
    {
        Queue::fake();

        $response = $this->withSession(['_token' => 'register-csrf'])->post(route('register.store'), [
            '_token' => 'register-csrf',
            'name' => 'Usuario OTP',
            'email' => 'otp@example.test',
            'password' => 'Secret12345!@#',
            'password_confirmation' => 'Secret12345!@#',
        ]);

        $response->assertRedirect(route('email.verify'));
        $user = User::query()->where('email', 'otp@example.test')->firstOrFail();
        $otp = EmailVerificationCode::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertTrue($user->requiresEmailVerification());
        $this->assertNotSame('482731', $otp->code_hash);
        $this->assertFalse(Hash::needsRehash($otp->code_hash));
        $this->assertDatabaseHas('email_logs', ['user_id' => $user->id, 'type' => EmailLog::TYPE_EMAIL_VERIFICATION, 'status' => EmailLog::QUEUED]);
        Queue::assertPushed(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->type === EmailLog::TYPE_EMAIL_VERIFICATION && $job->data['code'] !== '');
    }

    public function test_correct_code_verifies_once_and_old_code_cannot_be_reused(): void
    {
        Queue::fake();
        $this->withSession(['_token' => 'register-csrf'])->post(route('register.store'), [
            '_token' => 'register-csrf', 'name' => 'Usuario OTP', 'email' => 'verify@example.test',
            'password' => 'Secret12345!@#', 'password_confirmation' => 'Secret12345!@#',
        ]);
        $user = User::query()->where('email', 'verify@example.test')->firstOrFail();
        $plainCode = null;
        Queue::assertPushed(SendTransactionalEmail::class, function (SendTransactionalEmail $job) use (&$plainCode): bool {
            $plainCode = $job->data['code'];

            return true;
        });
        $this->assertIsString($plainCode);

        $this->withSession(['_token' => 'verify-csrf'])->actingAs($user)->post(route('email.verify.submit'), ['_token' => 'verify-csrf', 'code' => $plainCode])->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->requiresEmailVerification());

        $this->withSession(['_token' => 'verify-csrf'])->actingAs($user)->post(route('email.verify.submit'), ['_token' => 'verify-csrf', 'code' => $plainCode])->assertRedirect();
        $this->assertDatabaseCount('email_verification_codes', 1);
    }

    public function test_invalid_code_is_counted_and_login_redirects_pending_user(): void
    {
        Queue::fake();
        $user = User::factory()->create([
            'email' => 'pending@example.test',
            'email_verified_at' => null,
            'email_verification_required' => true,
            'password' => Hash::make('Secret12345!'),
        ]);

        $this->withSession(['_token' => 'login-csrf'])->post(route('login.store'), [
            '_token' => 'login-csrf', 'email' => $user->email, 'password' => 'Secret12345!',
        ])->assertRedirect(route('email.verify'));

        $this->withSession(['_token' => 'verify-csrf'])->actingAs($user)->post(route('email.verify.submit'), ['_token' => 'verify-csrf', 'code' => '000000'])->assertRedirect();
        $this->assertSame(1, EmailVerificationCode::query()->where('user_id', $user->id)->value('attempts'));
    }

    public function test_legacy_user_without_required_flag_keeps_access(): void
    {
        $user = User::factory()->create(['email_verified_at' => null, 'email_verification_required' => false]);

        $this->withSession(['_token' => 'legacy-csrf'])->post(route('login.store'), [
            '_token' => 'legacy-csrf', 'email' => $user->email, 'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}
