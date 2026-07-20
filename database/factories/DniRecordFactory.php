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
        return [
            'dni' => fake()->unique()->numerify('########'),
            'nombre_completo' => 'ANA PEREZ DIAZ',
            'nombres' => 'ANA',
            'apellido_paterno' => 'PEREZ',
            'apellido_materno' => 'DIAZ',
            'genero' => 'F',
            'fecha_nacimiento' => '1994-11-16',
            'codigo_verificacion' => '8',
            'source' => 'internal',
            'last_verified_at' => now(),
        ];
    }
}
