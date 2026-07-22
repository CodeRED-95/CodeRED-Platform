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
 * @property string|null $tipo_via
 * @property string|null $nombre_via
 * @property string|null $codigo_zona
 * @property string|null $tipo_zona
 * @property string|null $numero
 * @property string|null $interior
 * @property string|null $lote
 * @property string|null $departamento_direccion
 * @property string|null $manzana
 * @property string|null $kilometro
 */
class RucRecord extends Model
{
    protected $fillable = ['ruc', 'razon_social', 'estado', 'condicion', 'ubigeo', 'tipo_via', 'nombre_via', 'codigo_zona', 'tipo_zona', 'numero', 'interior', 'lote', 'departamento_direccion', 'manzana', 'kilometro', 'departamento', 'provincia', 'distrito', 'direccion'];
}
