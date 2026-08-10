<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    public function definition(): array
    {
        return [
            'name' => 'Token de pruebas',
            'description' => 'Token generado por factory.',
            'token' => hash('sha256', Str::random(40)),
            'abilities' => ['agencies:read'],
            'expires_at' => now()->addDays(30),
            'tokenable_type' => User::class,
            'tokenable_id' => User::factory(),
        ];
    }

    public function revoked(): self
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }
}
