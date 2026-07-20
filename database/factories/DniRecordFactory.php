<?php

namespace Database\Factories;

use App\Models\DniRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DniRecord> */
class DniRecordFactory extends Factory
{
    protected $model = DniRecord::class;

    public function definition(): array
    {
        $dni = fake()->unique()->numerify('########');

        return ['dni' => $dni, 'nombre_completo' => 'ANA PEREZ DIAZ', 'nombres' => 'ANA', 'apellido_paterno' => 'PEREZ', 'apellido_materno' => 'DIAZ', 'source' => 'internal', 'last_verified_at' => now()];
    }
}
