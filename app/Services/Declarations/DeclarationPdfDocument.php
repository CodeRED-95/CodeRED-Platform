<?php

declare(strict_types=1);

namespace App\Services\Declarations;

/**
 * Documento FPDF con el pie de página del original.
 *
 * FPDF no permite volver a una página ya escrita (no existe el SetPage de jsPDF):
 * el pie se dibuja mediante el gancho Footer(), que FPDF invoca al cerrar cada
 * página, y el total se resuelve con AliasNbPages().
 */
class DeclarationPdfDocument extends \FPDF
{
    private const MARGIN = 12.0;

    public string $generatedAt = '';

    public function Footer(): void // phpcs:ignore
    {
        // Discreto y en gris, como en el documento original: no compite con el contenido.
        $this->SetFont('Helvetica', '', 6.5);
        $this->SetTextColor(140, 140, 140);

        $left = $this->encode('Generado el '.$this->generatedAt.' · Declaración Jurada Shalom');
        $right = $this->encode('Página '.$this->PageNo().' de {nb}');

        $this->Text(self::MARGIN, $this->h - 5, $left);
        $this->Text($this->w - self::MARGIN - $this->GetStringWidth($right), $this->h - 5, $right);

        $this->SetTextColor(0, 0, 0);
    }

    private function encode(string $text): string
    {
        return mb_convert_encoding($text, 'CP1252', 'UTF-8');
    }
}
