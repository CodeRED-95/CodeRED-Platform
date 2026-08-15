<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Declaration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Declaración expuesta por la API.
 *
 * No serializa la ruta del PDF: el archivo vive en el disco privado y sólo se
 * entrega por el endpoint autenticado.
 */
class DeclarationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Declaration) {
            throw new LogicException('DeclarationResource requiere una declaración.');
        }

        $declaration = $this->resource;

        return [
            'id' => $declaration->id,
            'codigo' => sprintf('DJ-%s-%06d', $declaration->created_at?->format('Y') ?? date('Y'), $declaration->id),
            'remitente_dni' => $declaration->remitente_dni,
            'remitente_nombre' => $declaration->remitente_nombre,
            'destinatario_dni' => $declaration->destinatario_dni,
            'destinatario_nombre' => $declaration->destinatario_nombre,
            'agency_id' => $declaration->agency_id,
            'sede_destino' => $declaration->sede_destino,
            'motivo_envio' => $declaration->motivo_envio,
            'estado' => $declaration->hasPdf() ? 'generada' : 'pendiente',
            'pdf_available' => $declaration->hasPdf(),
            'pdf_nombre' => $declaration->pdfFileName(),
            'items' => $declaration->relationLoaded('items')
                ? $declaration->items->map(fn ($item): array => [
                    'cantidad' => $item->cantidad,
                    'descripcion' => $item->descripcion,
                ])->all()
                : [],
            'creada_en' => $declaration->created_at?->toIso8601String(),
        ];
    }

    public function with(Request $request): array
    {
        return ['success' => true];
    }
}
