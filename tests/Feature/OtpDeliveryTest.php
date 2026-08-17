<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ApiTokenRequests\CreateOtpTokenAction;
use App\Mail\OtpCodeMail;
use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Entrega del código de verificación que autoriza a revelar un token.
 *
 * Este camino estaba incompleto de tres formas a la vez: el envío del correo
 * era un TODO comentado, la plantilla mostraba un «123456» escrito a mano, y
 * pedir el código para una solicitud sin correo —las de Telegram o WhatsApp—
 * respondía con un error 500.
 */
class OtpDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function request(array $attributes = []): ApiTokenRequest
    {
        return ApiTokenRequest::query()->create(array_merge([
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tracking_code' => 'CR-'.strtoupper(bin2hex(random_bytes(5))),
            'requester_name' => 'Ada Lovelace',
            'requester_email' => 'ada@example.test',
            'application_name' => 'CODERED',
            'status' => 'approved',
            'requested_at' => now(),
        ], $attributes));
    }

    private function action(): CreateOtpTokenAction
    {
        return new CreateOtpTokenAction(new OtpService);
    }

    public function test_el_codigo_se_envia_por_correo_con_el_valor_real(): void
    {
        Mail::fake();

        $request = $this->request();

        $resultado = $this->action()->execute($request, '127.0.0.1', 'phpunit');

        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use ($request): bool {
            $code = $mail->otp->plainCode();

            // El código tiene que ser el real, de seis dígitos, y coincidir con
            // el hash guardado. La plantilla llegó a enviar un 123456 fijo.
            return $mail->request->is($request)
                && $code !== null
                && preg_match('/^\d{6}$/', $code) === 1
                && Hash::check($code, $mail->otp->code_hash);
        });

        $this->assertSame('a***@example.test', $resultado['email_masked']);
        $this->assertStringContainsString('a***@example.test', $resultado['message']);
    }

    public function test_una_solicitud_por_telegram_explica_el_canal_en_vez_de_reventar(): void
    {
        Mail::fake();

        $request = $this->request([
            'requester_email' => null,
            'delivery_telegram_username' => '@ada',
        ]);

        try {
            $this->action()->execute($request, '127.0.0.1', 'phpunit');
            $this->fail('Se esperaba una excepción explicando el canal de entrega.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Telegram', $exception->getMessage());
        }

        Mail::assertNothingSent();

        // Y no deja un código huérfano: si no se puede entregar, no se genera.
        $this->assertDatabaseCount('otp_validations', 0);
    }

    public function test_una_solicitud_sin_ningun_canal_pide_contactar_al_administrador(): void
    {
        Mail::fake();

        $request = $this->request(['requester_email' => null]);

        try {
            $this->action()->execute($request, '127.0.0.1', 'phpunit');
            $this->fail('Se esperaba una excepción.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('administrador', $exception->getMessage());
        }

        Mail::assertNothingSent();
    }

    public function test_el_codigo_en_claro_no_se_guarda_en_la_base(): void
    {
        Mail::fake();

        $request = $this->request();
        $this->action()->execute($request, '127.0.0.1', 'phpunit');

        $guardado = \App\Models\OtpValidation::query()->firstOrFail();

        // En la columna vive el hash; recuperar el código después es imposible
        // por diseño, y por eso el correo se envía en la misma petición.
        $this->assertNotEmpty($guardado->code_hash);
        $this->assertNull($guardado->fresh()->plainCode());
    }
}
