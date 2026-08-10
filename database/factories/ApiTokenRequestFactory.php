<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenRequestType;
use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\TokenVaultService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiTokenRequest>
 */
class ApiTokenRequestFactory extends Factory
{
    protected $model = ApiTokenRequest::class;

    public function definition(): array
    {
        $vault = new TokenVaultService;
        $email = $this->faker->unique()->safeEmail();

        return [
            'request_uuid' => (string) Str::uuid(),
            'tracking_code' => self::trackingCode(),
            'request_type' => ApiTokenRequestType::Issuance,
            'requester_name_encrypted' => $vault->encrypt($this->faker->name()),
            'requester_email_blind_index' => $vault->generateBlindIndex($email),
            'purpose_encrypted' => $vault->encrypt('Sincronización de agencias.'),
            'application_name' => 'Buscador Shalom Control',
            'requested_token_name' => 'Buscador Shalom Control',
            'requested_token_type' => 'agencies',
            'requested_abilities' => ['agencies:read'],
            'token_expires_in_days' => 30,
            'status' => ApiTokenRequestStatus::Pending,
            'requested_ip' => hash('sha256', $this->faker->ipv4()),
            'request_source' => 'public-web',
            'requested_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
            'delivery_channel' => 'email',
            'delivery_email' => $vault->encrypt($email),
            'metadata' => ['integration_type' => 'shalom-control-search'],
        ];
    }

    /**
     * Genera un código con el formato vigente: "CR-" + 10 caracteres.
     */
    public static function trackingCode(): string
    {
        return 'CR-'.strtoupper(Str::random(10));
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => ApiTokenRequestStatus::Approved,
            'approved_at' => now(),
            'reviewed_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::Pending,
        ]);
    }

    /**
     * Guarda un token cifrado con la clave del vault, igual que la aprobación real.
     */
    public function withCiphertext(string $plainToken = 'test-token-12345'): self
    {
        return $this->state(fn (): array => [
            'token_ciphertext' => (new TokenVaultService)->encrypt($plainToken),
            'token_hash' => hash('sha256', $plainToken),
            'token_last_four' => substr($plainToken, -4),
        ]);
    }
}
