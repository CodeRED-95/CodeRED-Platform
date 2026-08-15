<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Declaración Jurada simple para traslado de bienes.
 *
 * El documento se genera en el servidor a partir de estos datos: la fila es la
 * fuente de verdad y el PDF, una representación reproducible de ella.
 */
class Declaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agency_id',
        'remitente_dni',
        'remitente_nombre',
        'remitente_telefono',
        'destinatario_dni',
        'destinatario_nombre',
        'destinatario_telefono',
        'sede_destino',
        'motivo_envio',
        'pdf_path',
        'pdf_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'pdf_generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Referencia viva; puede ser null si la agencia se eliminó del catálogo. */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<DeclarationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DeclarationItem::class)->orderBy('position');
    }

    public function hasPdf(): bool
    {
        return $this->pdf_path !== null;
    }

    /**
     * Nombre de archivo para la descarga. Sólo documento y fecha: sin nombres
     * ni datos que no hagan falta para identificarlo.
     */
    public function pdfFileName(): string
    {
        $dni = preg_replace('/[^0-9A-Za-z]/', '', (string) $this->remitente_dni) ?: (string) $this->getKey();
        $date = ($this->created_at ?? now())->format('Ymd');

        return "declaracion-jurada-{$dni}-{$date}.pdf";
    }
}
