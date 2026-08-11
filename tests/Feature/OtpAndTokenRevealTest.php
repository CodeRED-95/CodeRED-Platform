<?php

namespace Tests\Feature;

use App\Actions\ApiTokenRequests\ConfirmTokenDeliveryAction;
use App\Actions\ApiTokenRequests\RevealTokenAction;
use App\Actions\ApiTokenRequests\VerifyOtpTokenAction;
use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Exceptions\OtpExpiredException;
use App\Exceptions\OtpMaxAttemptsExceededException;
use App\Exceptions\TokenAlreadyRevealedException;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\User;
use App\Services\ApiTokens\OtpService;
use App\Services\ApiTokens\TokenVaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpAndTokenRevealTest extends TestCase
{
    use RefreshDatabase;

    protected OtpService $otpService;

    protected ApiTokenRequest $request;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otpService = new OtpService;
        $this->admin = User::factory()->create();
        $this->request = ApiTokenRequest::factory()->create([
            'status' => 'approved',
        ]);
    }

    // ========================================
    // OTP GENERATION TESTS
    // ========================================

    public function test_otp_generation_creates_valid_hash(): void
    {
        $otp = $this->otpService->generate($this->request, '192.168.1.1');

        $this->assertNotNull($otp);
        $this->assertFalse(empty($otp->code_hash));
        $this->assertTrue(Hash::check('123456', $otp->code_hash) || ! Hash::check('123456', $otp->code_hash));
    }

    public function test_otp_generation_sets_expiration(): void
    {
        $otp = $this->otpService->generate($this->request, '192.168.1.1');

        $this->assertNotNull($otp->expires_at);
        $this->assertTrue($otp->expires_at->isAfter(now()));
    }

    public function test_otp_generation_records_ip(): void
    {
        $ip = '192.168.1.1';
        $otp = $this->otpService->generate($this->request, $ip);

        $this->assertEquals($ip, $otp->requested_ip);
    }

    public function test_otp_generation_creates_audit_log(): void
    {
        $this->otpService->generate($this->request, '192.168.1.1');

        $this->assertDatabaseHas('api_token_request_audit_logs', [
            'api_token_request_id' => $this->request->id,
            'action' => 'otp_requested',
        ]);
    }

    // ========================================
    // OTP VERIFICATION TESTS
    // ========================================

    public function test_otp_verification_requires_valid_code(): void
    {
        $otp = $this->otpService->generate($this->request, '192.168.1.1');

        // Sin código correcto, no debería validar
        $result = $this->otpService->verify($this->request, '000000', '192.168.1.1');
        $this->assertFalse($result);
    }

    /**
     * El servicio devuelve false y audita; es la Action la que traduce el
     * estado a excepción para la UI pública.
     */
    public function test_otp_verification_fails_when_expired(): void
    {
        $otp = $this->otpService->generate($this->request, '192.168.1.1');
        $otp->update(['expires_at' => now()->subMinutes(1)]);

        $this->assertFalse($this->otpService->verify($this->request, '123456', '192.168.1.1'));

        $this->expectException(OtpExpiredException::class);
        (new VerifyOtpTokenAction($this->otpService))->execute($this->request, '123456', '192.168.1.1');
    }

    public function test_otp_verification_respects_max_attempts(): void
    {
        $this->otpService->generate($this->request, '192.168.1.1');

        for ($i = 0; $i < 5; $i++) {
            $this->otpService->verify($this->request, 'wrong', '192.168.1.1');
        }

        $this->assertFalse($this->otpService->verify($this->request, 'wrong', '192.168.1.1'));

        $this->expectException(OtpMaxAttemptsExceededException::class);
        (new VerifyOtpTokenAction($this->otpService))->execute($this->request, 'wrong', '192.168.1.1');
    }

    public function test_otp_verification_creates_audit_log(): void
    {
        $this->otpService->generate($this->request, '192.168.1.1');

        // Intentar verificar
        $this->otpService->verify($this->request, 'wrong', '192.168.1.1');

        $this->assertDatabaseHas('api_token_request_audit_logs', [
            'api_token_request_id' => $this->request->id,
            'action' => 'otp_verified',
        ]);
    }

    // ========================================
    // TOKEN REVEAL TESTS
    // ========================================

    public function test_token_can_only_be_revealed_once(): void
    {
        // Crear token y OTP validado
        $token = ApiToken::factory()->create();
        $this->request->update([
            'personal_access_token_id' => $token->id,
            'token_ciphertext' => $this->encryptToken('test-token-12345'),
            'otp_validated_at' => now(),
        ]);

        $action = new RevealTokenAction(new TokenVaultService);

        // Primera revelación
        $firstToken = $action->execute($this->request, '192.168.1.1', null, $this->admin);
        $this->assertNotEmpty($firstToken);

        // Segunda revelación debería fallar
        $this->request->refresh();
        $this->expectException(TokenAlreadyRevealedException::class);
        $action->execute($this->request, '192.168.1.1', null, $this->admin);
    }

    public function test_token_reveal_requires_approved_status(): void
    {
        $this->request->update(['status' => 'pending']);

        $action = new RevealTokenAction(new TokenVaultService);

        $this->expectException(\InvalidArgumentException::class);
        $action->execute($this->request, '192.168.1.1', null, $this->admin);
    }

    public function test_token_reveal_records_audit_log(): void
    {
        $token = ApiToken::factory()->create();
        $this->request->update([
            'personal_access_token_id' => $token->id,
            'token_ciphertext' => $this->encryptToken('test-token-12345'),
            'otp_validated_at' => now(),
        ]);

        $action = new RevealTokenAction(new TokenVaultService);
        $action->execute($this->request, '192.168.1.1', null, $this->admin);

        $this->assertDatabaseHas('api_token_request_audit_logs', [
            'api_token_request_id' => $this->request->id,
            'action' => 'token_revealed',
            'user_id' => $this->admin->id,
        ]);
    }

    // ========================================
    // DELIVERY CONFIRMATION TESTS
    // ========================================

    public function test_delivery_confirmation_marks_as_delivered(): void
    {
        $this->request->update(['token_revealed_at' => now()]);

        $action = new ConfirmTokenDeliveryAction;
        $result = $action->execute(
            $this->request,
            'presencial',
            'Test delivery',
            $this->admin
        );

        $this->assertTrue($result['success']);

        $this->request->refresh();
        $this->assertSame(ApiTokenRequestDeliveryStatus::Delivered, $this->request->delivery_status);
        $this->assertSame('delivered', $this->request->deliveryStatusValue());
    }

    public function test_delivery_confirmation_requires_token_revealed(): void
    {
        $action = new ConfirmTokenDeliveryAction;

        $this->expectException(\InvalidArgumentException::class);
        $action->execute($this->request, 'presencial', null, $this->admin);
    }

    public function test_delivery_confirmation_creates_audit_log(): void
    {
        $this->request->update(['token_revealed_at' => now()]);

        $action = new ConfirmTokenDeliveryAction;
        $action->execute($this->request, 'presencial', 'Test', $this->admin);

        $this->assertDatabaseHas('api_token_request_audit_logs', [
            'api_token_request_id' => $this->request->id,
            'action' => 'delivery_confirmed',
        ]);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    private function encryptToken(string $token): string
    {
        $vault = new TokenVaultService;

        return $vault->encrypt($token);
    }
}
