<?php

namespace Database\Factories;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        $department = fake()->randomElement(['Tacna', 'Lima', 'Arequipa', 'Cusco', 'Piura']);
        $province = fake()->city();
        $district = fake()->citySuffix();
        $name = fake()->company().' Shalom';

        return [
            'code' => strtoupper('AG'.fake()->unique()->numerify('#####')),
            'name' => $name,
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
            'source_reference' => fake()->optional()->uuid(),
            'data_version' => 1,
            'last_verified_at' => now()->subDays(fake()->numberBetween(1, 365)),
        ];
    }
}
