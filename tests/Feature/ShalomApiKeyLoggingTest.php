<?php

namespace Tests\Feature;

use App\Modules\Shalom\Http\Middleware\AuthenticateShalomApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ShalomApiKeyLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_api_key_attempt_does_not_log_secret_prefix(): void
    {
        Route::post('/_testing/shalom-api-key', fn () => response()->json(['ok' => true]))
            ->middleware(AuthenticateShalomApiKey::class);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Invalid Shalom API key attempt'
                    && ($context['ip'] ?? null) === '127.0.0.1'
                    && ($context['credential_source'] ?? null) === 'x-shalom-api-key'
                    && ! array_key_exists('key_prefix', $context);
            });

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Shalom-API-Key', 'shalom_super_secret_token_1234567890')
            ->postJson('/_testing/shalom-api-key')
            ->assertUnauthorized();
    }
}
