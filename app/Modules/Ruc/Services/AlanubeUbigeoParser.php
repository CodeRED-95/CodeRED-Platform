<?php

namespace App\Modules\Ruc\Services;

use DOMDocument;
use DOMXPath;
use RuntimeException;

final class AlanubeUbigeoParser
{
    public function parse(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new RuntimeException('El contenido recibido de Alanube no es HTML válido.');
        }
        $rows = [];
        foreach ((new DOMXPath($document))->query('//tr[td]') ?: [] as $row) {
            $cells = [];
            foreach ((new DOMXPath($document))->query('./td', $row) ?: [] as $cell) {
                $cells[] = $this->normalize($cell->textContent);
            }
            $codigo = $cells[0] ?? '';
            if (! preg_match('/^\d{6}$/', $codigo)) {
                continue;
            }
            $offset = ($cells[1] ?? null) === '-' ? 2 : 1;
            if (count($cells) < $offset + 4) {
                throw new RuntimeException("La fila UBIGEO {$codigo} está incompleta.");
            }
            $rows[] = [
                'codigo' => $codigo,
                'departamento' => $cells[$offset],
                'provincia' => $cells[$offset + 1],
                'distrito' => $cells[$offset + 2],
                'capital' => $cells[$offset + 3] ?: null,
            ];
        }

        return $rows;
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }
}
