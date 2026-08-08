<?php

namespace Database\Factories\Modules\Ruc\Models;

use App\Modules\Ruc\Models\RucRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class RucRecordFactory extends Factory
{
    protected $model = RucRecord::class;

    public function definition(): array
    {
        $ruc = str_pad((string) $this->faker->numberBetween(10000000000, 99999999999), 11, '0', STR_PAD_LEFT);

        return [
            'ruc' => $ruc,
            'razon_social' => $this->faker->company(),
            'estado' => $this->faker->randomElement(['ACTIVO', 'HABIDO', 'CANCELADO']),
            'condicion' => $this->faker->randomElement(['PRINCIPAL', 'SECUNDARIA']),
            'departamento' => $this->faker->state(),
            'provincia' => $this->faker->city(),
            'distrito' => $this->faker->address(),
            'ubigeo' => str_pad((string) $this->faker->numberBetween(150000, 150999), 6, '0', STR_PAD_LEFT),
            'direccion' => $this->faker->streetAddress(),
        ];
    }
}
