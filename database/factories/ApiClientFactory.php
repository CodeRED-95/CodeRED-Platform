<?php

namespace Database\Factories;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApiClient> */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return ['name' => fake()->company(), 'description' => fake()->optional()->sentence(), 'contact_name' => fake()->name(), 'contact_email' => fake()->safeEmail(), 'active' => true];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
