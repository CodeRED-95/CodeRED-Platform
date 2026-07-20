<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DniRecord extends Model
{
    use HasFactory;

    protected $fillable = ['dni', 'nombre_completo', 'nombres', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento', 'edad', 'source', 'provider_reference', 'last_verified_at'];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date:Y-m-d', 'edad' => 'integer', 'last_verified_at' => 'datetime'];
    }
}
