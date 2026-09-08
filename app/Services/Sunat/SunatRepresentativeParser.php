<?php

namespace App\Services\Sunat;

use App\DTO\SunatRepresentativeData;
use App\Exceptions\SunatRepresentativeException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Normalizer;

class SunatRepresentativeParser
{
    /**
     * @return array<int, SunatRepresentativeData>
     */
    public function parse(string $html): array
    {
        if (trim($html) === '') {
            throw new SunatRepresentativeException('SUNAT devolvió una respuesta vacía.');
        }

        $encoding = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $tables = $xpath->query('//table');

        if ($tables === false || $tables->length === 0) {
            $plainText = $this->normalizeText(strip_tags($html));
            if (str_contains(mb_strtolower($plainText), 'no se encontraron') || str_contains(mb_strtolower($plainText), 'sin representantes')) {
                return [];
            }

            throw new SunatRepresentativeException('SUNAT devolvió HTML sin tablas interpretables.');
        }

        $representatives = [];
        foreach ($tables as $table) {
            $representatives = array_merge($representatives, $this->parseTable($table));
        }

        return array_values(array_filter($representatives));
    }

    /**
     * @return array<int, SunatRepresentativeData>
     */
    private function parseTable(DOMElement $table): array
    {
        $headers = [];
        $rows = [];

        foreach ($table->getElementsByTagName('tr') as $index => $tr) {
            $cells = [];
            foreach ($tr->childNodes as $node) {
                if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['th', 'td'], true)) {
                    $cells[] = $this->normalizeText($node->textContent);
                }
            }

            if ($cells === []) {
                continue;
            }

            if ($index === 0 || $this->looksLikeHeader($cells)) {
                $headers = $cells;

                continue;
            }

            $rows[] = $cells;
        }

        if ($rows === []) {
            return [];
        }

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = $this->mapRow($headers, $row);
        }

        return array_values(array_filter($mapped));
    }

    private function mapRow(array $headers, array $row): ?SunatRepresentativeData
    {
        $normalizedHeaders = array_map([$this, 'normalizeHeader'], $headers);
        $getByHeader = function (array $names) use ($normalizedHeaders, $row): ?string {
            foreach ($names as $name) {
                $index = array_search($name, $normalizedHeaders, true);
                if ($index !== false && isset($row[$index])) {
                    return $this->normalizeValue($row[$index]);
                }
            }

            return null;
        };

        $tipoDocumento = $getByHeader(['tipo documento', 'documento']) ?? $this->normalizeValue($row[0] ?? '');
        $numeroDocumento = $getByHeader(['numero documento', 'nro documento', 'documento numero']) ?? $this->normalizeValue($row[1] ?? '');
        $nombre = $getByHeader(['nombre', 'apellidos y nombres', 'representante']) ?? $this->normalizeValue($row[2] ?? '');
        $cargo = $getByHeader(['cargo', 'cargo / funcion', 'funcion']) ?? $this->normalizeValue($row[3] ?? '');
        $fechaDesde = $this->normalizeDate($getByHeader(['fecha desde', 'desde', 'fecha inicio']) ?? ($row[4] ?? null));

        if ($tipoDocumento === '' && $numeroDocumento === '' && $nombre === '' && $cargo === '') {
            return null;
        }

        return new SunatRepresentativeData(
            $tipoDocumento !== '' ? $tipoDocumento : 'DESCONOCIDO',
            $numeroDocumento !== '' ? $numeroDocumento : '',
            $nombre,
            $cargo,
            $fechaDesde,
        );
    }

    private function looksLikeHeader(array $cells): bool
    {
        $haystack = implode(' ', array_map([$this, 'normalizeHeader'], $cells));

        return str_contains($haystack, 'representante')
            || str_contains($haystack, 'documento')
            || str_contains($haystack, 'cargo')
            || str_contains($haystack, 'fecha');
    }

    private function normalizeHeader(string $value): string
    {
        return $this->normalizeValue($value);
    }

    private function normalizeText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        return $value;
    }

    private function normalizeValue(?string $value): string
    {
        return $this->normalizeText((string) $value);
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }
}
