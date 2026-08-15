<?php

declare(strict_types=1);

namespace App\Services\Declarations;

use App\Models\Declaration;

/**
 * Generación del PDF oficial de Declaración Jurada.
 *
 * Es un port fiel de packages/shalom-declaracion-jurada/src/pdf/buildDeclaracionPdf.js,
 * que dibujaba el documento con jsPDF por coordenadas en milímetros. Se eligió FPDF
 * —y no un motor HTML— porque comparte exactamente ese modelo: unidades en mm,
 * colocación absoluta y fuentes core Helvetica con las mismas métricas AFM que usa
 * jsPDF. Gracias a eso los anchos medidos coinciden, y con ellos la justificación
 * de los párrafos y la longitud de los subrayados, que dependen del texto medido.
 *
 * Las coordenadas, tamaños de fuente e incrementos verticales se mantienen tal cual
 * estaban en el original: cualquier cambio aquí altera un documento legal.
 */
class DeclarationPdfBuilder
{
    /** #e31837 — el mismo rojo de marca que usaba el original. */
    private const SHALOM_RED = [227, 24, 55];

    /** Separación fija etiqueta→valor; el original la fijó por fiabilidad de medida. */
    private const LABEL_VALUE_GAP_MM = 2.2;

    private const MARGIN = 12.0;

    private DeclarationPdfDocument $pdf;

    /** FPDF no expone su tamaño de fuente, así que se lleva aquí. */
    private float $fontSize = 10.3;

    private float $colWidth;

    private float $titleCenterX;

    public function build(Declaration $declaration): string
    {
        $this->pdf = new DeclarationPdfDocument('P', 'mm', 'A4');
        $this->pdf->generatedAt = now()->format('d/m/Y H:i');
        $this->pdf->AliasNbPages();
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $this->pdf->AddPage();

        $pageWidth = 210.0;
        $pageHeight = 297.0;
        $this->colWidth = $pageWidth - (self::MARGIN * 2);
        $this->titleCenterX = self::MARGIN + ($this->colWidth / 2);
        $bottomLimit = $pageHeight - self::MARGIN;

        $currentY = $this->drawHeaderAndTitle();
        $currentY = $this->drawSender($declaration, $currentY);
        $currentY = $this->drawRecipient($declaration, $currentY);
        $currentY = $this->drawItemsTable($declaration, $currentY);
        $currentY = $this->drawLegalText($currentY);
        $this->drawSignatureBlock($declaration, $currentY, $bottomLimit);

        return $this->pdf->Output('S');
    }

    // ---------------------------------------------------------------- bloques

    private function drawHeaderAndTitle(): float
    {
        $headerTop = 8.0;
        $headerHeight = 10.0;

        [$r, $g, $b] = self::SHALOM_RED;
        $this->pdf->SetFillColor($r, $g, $b);
        $this->pdf->Rect(self::MARGIN, $headerTop, $this->colWidth, $headerHeight, 'F');

        $this->pdf->SetTextColor(255, 255, 255);
        $this->font('B', 13);
        $this->text(self::MARGIN + 3, $headerTop + ($headerHeight / 2) + 1.6, 'SHALOM');

        $this->font('', 7.5);
        $this->textRight(self::MARGIN + $this->colWidth - 3, $headerTop + ($headerHeight / 2) + 1, 'DECLARACIÓN JURADA');

        $this->pdf->SetTextColor(0, 0, 0);
        $this->font('B', 11);

        $title = 'DECLARACIÓN JURADA SIMPLE PARA TRASLADO DE BIENES - USO PERSONAL';
        $titleLines = $this->wrap($title, $this->colWidth - 4);
        $titleY = $headerTop + $headerHeight + 6;

        foreach ($titleLines as $index => $line) {
            $this->textCenter($this->titleCenterX, $titleY + ($index * 4.8), $line);
        }

        return $titleY + (count($titleLines) * 4.8) + 4.5;
    }

    private function drawSender(Declaration $declaration, float $currentY): float
    {
        $this->font('', 10.3);
        $this->writeJustified('Por el presente documento de carácter, de declaración jurada', self::MARGIN, $currentY, $this->colWidth, 1.25);
        $currentY += 7.5;

        // "YO" pegado al margen izquierdo y el nombre centrado en la MISMA fila,
        // con la línea corriendo desde justo después de "YO" hasta el margen derecho.
        $this->font('', 10.3);
        $this->text(self::MARGIN, $currentY, 'YO');
        $yoWidth = $this->pdf->GetStringWidth($this->enc('YO'));

        $this->font('B', 10.3);
        $this->textCenter($this->titleCenterX, $currentY, mb_strtoupper($declaration->remitente_nombre));
        $this->pdf->Line(self::MARGIN + $yoWidth + 3, $currentY + 1.4, self::MARGIN + $this->colWidth, $currentY + 1.4);
        $currentY += 7.5;

        $this->font('', 10.3);
        $this->writeJustifiedSegments([
            ['identificado con documento de identificación', ''],
            ['(DNI, CARNET DE EXTRANJERÍA)', 'B'],
            [$declaration->remitente_dni, ''],
        ], self::MARGIN, $currentY, $this->colWidth, 1.2);
        $currentY += 7.5;

        $this->labeledField('con Teléfono', (string) $declaration->remitente_telefono, self::MARGIN, $currentY, minLineWidth: 34);

        return $currentY + 14;
    }

    private function drawRecipient(Declaration $declaration, float $currentY): float
    {
        $this->font('B', 10.3);
        $this->text(self::MARGIN, $currentY, 'DECLARO BAJO JURAMENTO');
        $currentY += 11;

        $date = $declaration->created_at ?? now();

        $this->font('', 10.3);
        $lines = $this->writeJustifiedSegments([
            ['Fecha '.$date->format('d/m/Y').' autorizo el traslado de mis bienes a través de la', ''],
            ['EMPRESA DE TRANSPORTE SHALOM EMPRESARIAL S.A.C con RUC: 20512528458', 'B'],
            [', para el', ''],
        ], self::MARGIN, $currentY, $this->colWidth, 1.25);
        $currentY += ($lines * 5.6) + 3;

        $this->font('', 10.3);
        $this->text(self::MARGIN, $currentY, 'Señor(a):');
        $srPrefixWidth = $this->pdf->GetStringWidth($this->enc('Señor(a):'));

        $this->font('B', 10.3);
        $this->textCenter($this->titleCenterX, $currentY, mb_strtoupper($declaration->destinatario_nombre));
        $this->pdf->Line(self::MARGIN + $srPrefixWidth + 3, $currentY + 1.4, self::MARGIN + $this->colWidth, $currentY + 1.4);
        $currentY += 7.5;

        $this->font('', 10.3);
        $this->labeledField('con DNI N°', (string) $declaration->destinatario_dni, self::MARGIN, $currentY, lineEndX: self::MARGIN + ($this->colWidth * 0.48));
        $this->labeledField('con teléfono', (string) $declaration->destinatario_telefono, self::MARGIN + ($this->colWidth * 0.54), $currentY, lineEndX: self::MARGIN + $this->colWidth);
        $currentY += 7.5;

        $this->labeledField(
            'y para la oficina de',
            mb_strtoupper($declaration->sede_destino),
            self::MARGIN,
            $currentY,
            lineEndX: self::MARGIN + $this->colWidth,
            bold: true
        );

        return $currentY + 14;
    }

    private function drawItemsTable(Declaration $declaration, float $currentY): float
    {
        $this->font('B', 10.3);
        $this->text(self::MARGIN, $currentY - 2, 'DECLARO ENVIAR LO SIGUIENTE:');

        $quantityWidth = 27.0;
        $descriptionWidth = $this->colWidth - $quantityWidth;
        $rowHeight = 6.6;

        // Cabecera roja con texto blanco, como en el original.
        [$r, $g, $b] = self::SHALOM_RED;
        $this->pdf->SetFillColor($r, $g, $b);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->font('B', 9.3);
        $this->pdf->SetDrawColor(190, 190, 190);
        $this->pdf->SetLineWidth(0.2);

        $this->pdf->SetXY(self::MARGIN, $currentY);
        $this->pdf->Cell($quantityWidth, $rowHeight, $this->enc('CANT.'), 1, 0, 'C', true);
        $this->pdf->Cell($descriptionWidth, $rowHeight, $this->enc('DESCRIPCIÓN DE LOS BIENES'), 1, 1, 'C', true);

        $this->pdf->SetTextColor(0, 0, 0);
        $this->font('', 8.8);

        foreach ($declaration->items as $item) {
            $this->pdf->SetX(self::MARGIN);
            $this->pdf->Cell($quantityWidth, $rowHeight, $this->enc((string) $item->cantidad), 1, 0, 'C');
            $this->pdf->Cell($descriptionWidth, $rowHeight, $this->enc($item->descripcion), 1, 1, 'L');
        }

        // Última fila en negrita con el motivo, igual que el didParseCell original.
        $this->font('B', 8.8);
        $this->pdf->SetX(self::MARGIN);
        $this->pdf->Cell($quantityWidth, $rowHeight, '', 1, 0, 'C');
        $this->pdf->Cell(
            $descriptionWidth,
            $rowHeight,
            $this->enc('MOTIVO DEL ENVÍO: '.mb_strtoupper((string) $declaration->motivo_envio)),
            1,
            1,
            'L'
        );

        $this->pdf->SetDrawColor(0, 0, 0);

        return $this->pdf->GetY() + 9;
    }

    private function drawLegalText(float $currentY): float
    {
        // Texto legal literal: no se reescribe ni se reformula.
        $this->font('', 9.3);

        $legal = 'Así mismo, declaro bajo juramento que los presentes datos obedecen a la verdad, sometiéndome a las sanciones administrativas, civiles y penales que correspondan en caso de falsedad de los mismos, de acuerdo con lo regulado por la';
        $legalLines = $this->writeJustified($legal, self::MARGIN, $currentY, $this->colWidth, 1.25);
        $currentY += ($legalLines * 4.6) + 2;

        $this->font('B', 9.3);
        $this->text(self::MARGIN, $currentY, 'Ley N° 27444 - Ley del Procedimiento Administrativo General.');
        $currentY += 7;

        $this->font('', 9.3);
        $conformity = 'Para mayor constancia y validez en cumplimiento de lo indicado, en señal de conformidad firmo esta declaración y coloco mi huella digital para los fines pertinentes.';
        $conformityLines = $this->writeJustified($conformity, self::MARGIN, $currentY, $this->colWidth, 1.25);

        return $currentY + ($conformityLines * 4.6) + 11;
    }

    private function drawSignatureBlock(Declaration $declaration, float $finalY, float $bottomLimit): void
    {
        // El bloque nunca se parte entre páginas: si no cabe entero, se abre una nueva.
        $signatureBlockHeight = 8 + 20 + (2 * 8) + 11;

        if ($finalY + $signatureBlockHeight > $bottomLimit) {
            $this->pdf->AddPage();
            $finalY = self::MARGIN;
        }

        $signatureY = $finalY + 8;
        $date = $declaration->created_at ?? now();

        $this->font('', 9.3);
        $this->pdf->Line(self::MARGIN + ($this->colWidth * 0.4), $signatureY, self::MARGIN + ($this->colWidth * 0.55), $signatureY);
        $this->textCenter(self::MARGIN + ($this->colWidth * 0.475), $signatureY - 1, $date->format('d'));
        $this->text(self::MARGIN + ($this->colWidth * 0.61), $signatureY, 'de');
        $this->pdf->Line(self::MARGIN + ($this->colWidth * 0.69), $signatureY, self::MARGIN + ($this->colWidth * 0.83), $signatureY);
        $this->textCenter(self::MARGIN + ($this->colWidth * 0.76), $signatureY - 1, $this->monthName((int) $date->format('n')));
        $this->text(self::MARGIN + ($this->colWidth * 0.86), $signatureY, 'del '.$date->format('Y'));

        $huellaWidth = 34.0;
        $huellaHeight = 40.0;
        $huellaX = self::MARGIN + $this->colWidth - $huellaWidth - 5;
        $huellaY = $signatureY + 8;

        $baseFontSize = 9.3;
        $labelValueX = self::MARGIN + 30;
        $labelValueMaxWidth = $huellaX - $labelValueX - 6;

        $values = ['', mb_strtoupper($declaration->remitente_nombre), mb_strtoupper($declaration->remitente_dni)];

        foreach (['Firma:', 'Nombres:', 'N° Documento:'] as $index => $label) {
            $y = $signatureY + (20 + ($index * 8));

            $this->font('', $baseFontSize);
            $this->text(self::MARGIN, $y, $label);

            $value = $values[$index];

            if ($value !== '') {
                // Nunca se envuelve a una segunda línea: se reduce el cuerpo hasta que quepa.
                $this->fitSingleLine($value, $labelValueMaxWidth, $baseFontSize);
                $this->text($labelValueX, $y - 1, $value);
            }

            $this->font('', $baseFontSize);
            $this->pdf->Line($labelValueX - 1, $y + 1, $labelValueX + $labelValueMaxWidth, $y + 1);
        }

        $this->pdf->Rect($huellaX, $huellaY, $huellaWidth, $huellaHeight);
        $this->font('I', 6.5);
        $this->textCenter($huellaX + ($huellaWidth / 2), $huellaY + $huellaHeight + 4, 'Huella Digital');
        $this->font('', 9.3);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Justificación completa repartiendo el sobrante entre las palabras.
     * La ÚLTIMA línea nunca se justifica, como en el original.
     */
    private function writeJustified(string $text, float $x, float $y, float $width, float $lineHeightFactor): int
    {
        $lines = $this->wrap($text, $width);
        $lineHeight = $this->currentFontSize() * 0.3528 * $lineHeightFactor;

        foreach ($lines as $index => $line) {
            $lineY = $y + ($index * $lineHeight);
            $words = preg_split('/\s+/', trim($line)) ?: [];
            $isLast = $index === count($lines) - 1;

            if ($isLast || count($words) < 2) {
                $this->text($x, $lineY, trim($line));

                continue;
            }

            $wordsWidth = array_sum(array_map(fn (string $w): float => $this->pdf->GetStringWidth($this->enc($w)), $words));
            $gap = max(0.0, ($width - $wordsWidth) / (count($words) - 1));
            $cursorX = $x;

            foreach ($words as $word) {
                $this->text($cursorX, $lineY, $word);
                $cursorX += $this->pdf->GetStringWidth($this->enc($word)) + $gap;
            }
        }

        return count($lines);
    }

    /**
     * Igual que writeJustified pero con tramos que alternan normal y negrita.
     *
     * @param  list<array{0: string, 1: string}>  $segments  [texto, estilo]
     */
    private function writeJustifiedSegments(array $segments, float $x, float $y, float $width, float $lineHeightFactor): int
    {
        $size = $this->currentFontSize();
        $tokens = [];

        foreach ($segments as [$text, $style]) {
            foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
                if ($word !== '') {
                    $tokens[] = ['text' => $word, 'style' => $style];
                }
            }
        }

        $this->font('', $size);
        $minimumSpace = $this->pdf->GetStringWidth(' ');

        $lines = [];
        $currentLine = [];
        $currentWidth = 0.0;

        foreach ($tokens as $token) {
            $this->font($token['style'], $size);
            $tokenWidth = $this->pdf->GetStringWidth($this->enc($token['text']));
            $nextWidth = $currentWidth + ($currentLine !== [] ? $minimumSpace : 0) + $tokenWidth;

            if ($currentLine !== [] && $nextWidth > $width) {
                $lines[] = $currentLine;
                $currentLine = [];
                $currentWidth = 0.0;
            }

            $token['width'] = $tokenWidth;
            $currentLine[] = $token;
            $currentWidth += (count($currentLine) > 1 ? $minimumSpace : 0) + $tokenWidth;
        }

        if ($currentLine !== []) {
            $lines[] = $currentLine;
        }

        $lineHeight = $size * 0.3528 * $lineHeightFactor;

        foreach ($lines as $lineIndex => $line) {
            $lineY = $y + ($lineIndex * $lineHeight);
            $isLast = $lineIndex === count($lines) - 1;
            $gap = ($isLast || count($line) < 2)
                ? $minimumSpace
                : max(0.0, ($width - array_sum(array_column($line, 'width'))) / (count($line) - 1));

            $cursorX = $x;

            foreach ($line as $token) {
                $this->font($token['style'], $size);
                $this->text($cursorX, $lineY, $token['text']);
                $cursorX += $token['width'] + $gap;
            }
        }

        $this->font('', $size);

        return count($lines);
    }

    /** Fila "etiqueta + valor" con subrayado bajo el valor. */
    private function labeledField(
        string $label,
        string $value,
        float $x,
        float $y,
        float $minLineWidth = 0,
        ?float $lineEndX = null,
        bool $bold = false
    ): void {
        $size = $this->currentFontSize();

        $this->font('', $size);
        $this->text($x, $y, $label);
        $labelWidth = $this->pdf->GetStringWidth($this->enc($label)) + self::LABEL_VALUE_GAP_MM;
        $valueX = $x + $labelWidth;

        $this->font($bold ? 'B' : '', $size);
        $this->text($valueX, $y, $value);
        $valueWidth = $this->pdf->GetStringWidth($this->enc($value));

        $end = $lineEndX ?? ($valueX + max($valueWidth, $minLineWidth));
        $this->pdf->Line($valueX - 1, $y + 1.4, $end, $y + 1.4);

        $this->font('', $size);
    }

    private function fitSingleLine(string $text, float $maxWidth, float $baseFontSize, float $minFontSize = 6.5): void
    {
        $size = $baseFontSize;
        $this->font('', $size);

        while ($size > $minFontSize && $this->pdf->GetStringWidth($this->enc($text)) > $maxWidth) {
            $size -= 0.3;
            $this->font('', $size);
        }
    }

    /** @return list<string> */
    private function wrap(string $text, float $width): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && $this->pdf->GetStringWidth($this->enc($candidate)) > $width) {
                $lines[] = $current;
                $current = $word;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    private function font(string $style, ?float $size = null): void
    {
        if ($size !== null) {
            $this->fontSize = $size;
        }

        $this->pdf->SetFont('Helvetica', $style, $this->fontSize);
    }

    private function currentFontSize(): float
    {
        return $this->fontSize;
    }

    private function text(float $x, float $y, string $text): void
    {
        $this->pdf->Text($x, $y, $this->enc($text));
    }

    private function textCenter(float $centerX, float $y, string $text): void
    {
        $this->pdf->Text($centerX - ($this->pdf->GetStringWidth($this->enc($text)) / 2), $y, $this->enc($text));
    }

    private function textRight(float $rightX, float $y, string $text): void
    {
        $this->pdf->Text($rightX - $this->pdf->GetStringWidth($this->enc($text)), $y, $this->enc($text));
    }

    /**
     * Las fuentes core de FPDF son cp1252, igual que el WinAnsi que usa jsPDF.
     * La conversión mantiene acentos, "°" y "·" idénticos al documento original.
     */
    private function enc(string $text): string
    {
        return mb_convert_encoding($text, 'CP1252', 'UTF-8');
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ][$month] ?? '';
    }
}
