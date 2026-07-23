<?php

namespace Database\Factories;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Agency> */
class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        $department = fake()->randomElement(['Tacna', 'Lima', 'Arequipa', 'Cusco', 'Piura']);
        $province = fake()->city();
        $district = fake()->citySuffix();
        $name = fake()->company().' Shalom';

        $externalId = fake()->unique()->numberBetween(1, 999999999);

        return [
            'external_id' => $externalId,
            'code' => strtoupper('AG'.fake()->unique()->numerify('#####')),
            'name' => $name,
            'old_name' => fake()->optional(0.3)->company(),
            'short_name' => Str::limit($name, 30, ''),
            'slug' => Str::slug($name.' '.fake()->unique()->numerify('###')),
            'department' => $department,
            'province' => $province,
            'district' => $district,
            'address' => fake()->address(),
            'reference' => fake()->optional()->sentence(),
            'phone' => fake()->optional()->phoneNumber(),
            'secondary_phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'schedule' => fake()->optional()->sentence(),
            'latitude' => fake()->optional()->latitude(-18, -3),
            'longitude' => fake()->optional()->longitude(-82, -68),
            'services' => ['Envíos', 'Paquetería'],
            'observations' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(AgencyStatus::cases())->value,
            'source' => 'seed',
            'source_reference' => (string) $externalId,
            'texto_chosen_terrestre' => fake()->boolean() ? fake()->sentence().' - TERRESTRE' : null,
            'texto_chosen_aereo' => fake()->boolean() ? fake()->sentence().' - AEREO' : null,
            'data_version' => 1,
            'last_verified_at' => now()->subDays(fake()->numberBetween(1, 365)),
        ];
    }

    public function terrestre(): static
    {
        return $this->state(fn (array $attributes): array => [
            'texto_chosen_terrestre' => $attributes['external_id'].' - TERRESTRE',
            'texto_chosen_aereo' => null,
        ]);
    }

    public function aereo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'texto_chosen_terrestre' => null,
            'texto_chosen_aereo' => $attributes['external_id'].' - AEREO',
        ]);
    }

    public function sinIdentificadorExtension(): static
    {
        return $this->state(fn (): array => [
            'texto_chosen_terrestre' => null,
            'texto_chosen_aereo' => null,
        ]);
    }
}
