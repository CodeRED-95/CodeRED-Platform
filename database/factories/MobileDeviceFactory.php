<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MobileDevice>
 */
class MobileDeviceFactory extends Factory
{
    protected $model = MobileDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = 'fcm-'.Str::random(140);

        return [
            'user_id' => User::factory(),
            'platform' => MobileDevice::PLATFORM_ANDROID,
            'push_token' => $token,
            'push_token_hash' => MobileDevice::hashToken($token),
            'device_name' => 'Pixel 7',
            'app_version' => '0.14.0',
            'last_seen_at' => now(),
        ];
    }

    /** Un dispositivo con un token conocido, para poder afirmar sobre él. */
    public function withToken(string $token): self
    {
        return $this->state(fn (): array => [
            'push_token' => $token,
            'push_token_hash' => MobileDevice::hashToken($token),
        ]);
    }
}
