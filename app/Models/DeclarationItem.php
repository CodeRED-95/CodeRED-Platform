<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una fila de la tabla "DECLARO ENVIAR LO SIGUIENTE" del documento. */
class DeclarationItem extends Model
{
    /** @use HasFactory<\Database\Factories\DeclarationItemFactory> */
    use HasFactory;

    protected $fillable = [
        'declaration_id',
        'cantidad',
        'descripcion',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(Declaration::class);
    }
}
