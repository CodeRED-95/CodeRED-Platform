<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Escritor mínimo de archivos .xlsx sin dependencias externas.
 *
 * Un .xlsx es un ZIP con partes XML (Office Open XML). El proyecto no incluye
 * PhpSpreadsheet ni similares, así que se genera aquí un libro de una sola hoja
 * con cadenas en línea (inlineStr), suficiente para exportar registros
 * tabulares. Evita añadir una dependencia y un rebuild solo para esto.
 *
 * Uso:
 *   (new SimpleXlsxWriter('Lote'))
 *       ->addRow(['Fecha', 'Campo', 'Valor'])   // encabezado
 *       ->addRow(['2026-08-10 10:00', 'DNI', '12345678'])
 *       ->saveTo($absolutePath);
 */
final class SimpleXlsxWriter
{
    /** @var array<int, array<int, string>> */
    private array $rows = [];

    public function __construct(private string $sheetName = 'Hoja1')
    {
        // Excel limita el nombre de hoja a 31 caracteres y prohíbe : \ / ? * [ ].
        $this->sheetName = mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', ' ', $sheetName) ?: 'Hoja1', 0, 31);
    }

    /**
     * @param  array<int, string|int|float|null>  $cells
     */
    public function addRow(array $cells): self
    {
        $this->rows[] = array_map(static fn ($cell): string => $cell === null ? '' : (string) $cell, array_values($cells));

        return $this;
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function addRows(array $rows): self
    {
        foreach ($rows as $row) {
            $this->addRow($row);
        }

        return $this;
    }

    public function saveTo(string $absolutePath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear el archivo XLSX en {$absolutePath}.");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());

        if ($zip->close() !== true) {
            throw new RuntimeException('No se pudo finalizar el archivo XLSX.');
        }
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape($this->sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        // Estilo 0: normal. Estilo 1: negrita (para el encabezado).
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }

    private function sheet(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($this->rows as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            $styleId = $rowIndex === 0 ? 1 : 0; // primera fila en negrita
            $xml .= '<row r="'.$rowNumber.'">';

            foreach ($cells as $colIndex => $value) {
                $ref = $this->columnLetter($colIndex).$rowNumber;
                $xml .= '<c r="'.$ref.'" s="'.$styleId.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;

        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function escape(string $value): string
    {
        // Se retiran caracteres de control no válidos en XML antes de escapar.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
