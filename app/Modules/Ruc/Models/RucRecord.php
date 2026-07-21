<?php

namespace App\Modules\Ruc\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $ruc
 * @property string $razon_social
 * @property string|null $estado
 * @property string|null $condicion
 * @property string|null $ubigeo
 * @property string|null $direccion
 * @property string|null $departamento
 * @property string|null $provincia
 * @property string|null $distrito
 */
class RucRecord extends Model
{
    protected $fillable = ['ruc', 'razon_social', 'estado', 'condicion', 'ubigeo', 'tipo_via', 'nombre_via', 'codigo_zona', 'tipo_zona', 'numero', 'interior', 'lote', 'departamento_direccion', 'manzana', 'kilometro', 'departamento', 'provincia', 'distrito', 'direccion'];
}
