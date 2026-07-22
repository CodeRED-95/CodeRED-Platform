<?php

namespace App\Modules\Ruc\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $codigo
 * @property string $departamento
 * @property string $provincia
 * @property string $distrito
 */
class Ubigeo extends Model
{
    protected $fillable = ['codigo', 'departamento', 'provincia', 'distrito', 'capital', 'source', 'source_url', 'source_updated_at'];

    protected function casts(): array
    {
        return ['source_updated_at' => 'datetime'];
    }
}
