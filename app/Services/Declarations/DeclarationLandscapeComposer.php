<?php

declare(strict_types=1);

namespace App\Services\Declarations;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Compone la versión apaisada: la declaración a la izquierda, la foto del DNI
 * a la derecha.
 *
 * No vuelve a dibujar el documento. Toma el PDF vertical **ya generado** por
 * DeclarationPdfBuilder y lo coloca como plantilla escalada dentro de una
 * página A4 horizontal. Esa es la razón de ser de esta clase: el documento
 * oficial se sigue produciendo en un único sitio, y aquí no hay ninguna copia
 * del texto legal, de la tabla ni de la firma que pueda quedar desincronizada.
 * Al ser vectorial, escalarlo no pierde nitidez ni deforma nada.
 *
 * Si la declaración ocupara más de una página, cada una recibe su hoja
 * apaisada; la foto acompaña sólo a la primera, que es donde está el titular.
 */
final class DeclarationLandscapeComposer
{
    /** A4 apaisado, en milímetros. */
    private const PAGE_WIDTH = 297.0;

    private const PAGE_HEIGHT = 210.0;

    private const MARGIN = 8.0;

    /** Separación entre el documento y la foto. */
    private const GUTTER = 8.0;

    /**
     * @param  string  $portraitPdf  contenido binario del PDF vertical
     * @param  string  $photoPath    ruta absoluta a la imagen ya validada
     * @return string  contenido binario del PDF apaisado
     */
    public function compose(string $portraitPdf, string $photoPath): string
    {
        $pdf = new Fpdi('L', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile(StreamReader::createByString($portraitPdf));

        $usableHeight = self::PAGE_HEIGHT - (self::MARGIN * 2);

        for ($page = 1; $page <= $pageCount; $page++) {
            $pdf->AddPage();

            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);

            // La altura manda: es la dimensión que más se reduce al pasar de
            // vertical a apaisado. El ancho se deriva de ella para que la
            // proporción del documento no cambie.
            $scale = $usableHeight / $size['height'];
            $documentWidth = $size['width'] * $scale;

            $pdf->useTemplate($template, self::MARGIN, self::MARGIN, $documentWidth, $usableHeight);

            if ($page === 1) {
                $this->drawPhoto($pdf, $photoPath, self::MARGIN + $documentWidth + self::GUTTER, $usableHeight);
            }
        }

        return (string) $pdf->Output('S');
    }

    /**
     * Coloca la foto ajustada a la caja disponible sin deformarla: se escala
     * por el lado que primero toca el borde y se centra en el otro.
     */
    private function drawPhoto(Fpdi $pdf, string $photoPath, float $boxX, float $boxHeight): void
    {
        $boxWidth = self::PAGE_WIDTH - self::MARGIN - $boxX;

        if ($boxWidth <= 0) {
            return;
        }

        $dimensions = @getimagesize($photoPath);

        if ($dimensions === false || $dimensions[0] <= 0 || $dimensions[1] <= 0) {
            return;
        }

        [$pixelWidth, $pixelHeight] = $dimensions;

        $scale = min($boxWidth / $pixelWidth, $boxHeight / $pixelHeight);
        $width = $pixelWidth * $scale;
        $height = $pixelHeight * $scale;

        $x = $boxX + (($boxWidth - $width) / 2);
        $y = self::MARGIN + (($boxHeight - $height) / 2);

        $pdf->Image($photoPath, $x, $y, $width, $height);

        // Marco discreto: sin él la foto flota sobre el blanco y no se
        // distingue del papel cuando el documento se imprime.
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($x, $y, $width, $height);
        $pdf->SetDrawColor(0, 0, 0);
    }
}
