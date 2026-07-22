<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Support\RucPadronParser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class RucIncomingFileValidator
{
    public function __construct(private readonly RucIncomingFileScanner $scanner, private readonly RucPadronParser $parser) {}

    public function validate(string $path): array
    {
        $path = $this->scanner->resolveIncomingPath($path);
        $disk = Storage::disk((string) config('ruc.import.disk'));
        $size = $disk->size($path);
        if ($size < 1) {
            throw ValidationException::withMessages(['incomingFiles' => 'El archivo TXT está vacío.']);
        }
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw ValidationException::withMessages(['incomingFiles' => 'No se pudo leer el archivo seleccionado.']);
        }
        try {
            $sample = (string) fread($stream, 65536);
        } finally {
            fclose($stream);
        }
        $encoding = str_starts_with($sample, "\xEF\xBB\xBF") || mb_check_encoding($sample, 'UTF-8') ? 'UTF-8' : (mb_detect_encoding($sample, ['Windows-1252', 'ISO-8859-1'], true) ?: 'Windows-1252');
        $utf8 = mb_convert_encoding($sample, 'UTF-8', $encoding);
        $lines = preg_split('/\r\n|\n|\r/', $utf8, 21) ?: [];
        $lines = array_values(array_filter(array_slice($lines, 0, 20), fn (string $line): bool => trim($line) !== ''));
        $header = preg_replace('/^\x{FEFF}/u', '', $lines[0] ?? '') ?? '';
        $counts = ['|' => substr_count($header, '|'), ';' => substr_count($header, ';'), "\t" => substr_count($header, "\t"), ',' => substr_count($header, ',')];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);
        $columns = str_getcsv($header, $delimiter);
        $normalized = mb_strtoupper(implode('|', $columns));
        $headerValid = mb_strtoupper(trim($columns[0] ?? '')) === 'RUC' && (str_contains($normalized, 'NOMBRE O RAZÓN SOCIAL') || str_contains($normalized, 'NOMBRE O RAZON SOCIAL') || str_contains($normalized, 'RAZON SOCIAL'));
        $preview = [];
        $warnings = [];
        foreach (array_slice($lines, 1, 5) as $line) {
            $parsed = $this->parser->parse($line, $delimiter, 'UTF-8');
            if (isset($parsed['error'])) {
                $warnings[] = $parsed['error'];
            }
            $preview[] = mb_substr($line, 0, 300);
        }
        $valid = $headerValid && $delimiter === '|' && $preview !== [] && $warnings === [];
        $averageBytes = count($lines) > 1 ? max(1, (int) (strlen($sample) / count($lines))) : max(1, strlen($sample));

        return ['valid' => $valid, 'message' => $valid ? 'Archivo válido.' : ($headerValid ? 'Las filas de muestra no cumplen el formato RUC.' : 'La cabecera no corresponde al padrón reducido RUC.'), 'size' => $size, 'encoding' => $encoding, 'delimiter' => $delimiter, 'header' => mb_substr($header, 0, 500), 'columns' => count($columns), 'preview' => $preview, 'warnings' => array_values(array_unique($warnings)), 'estimated_rows' => max(0, (int) floor($size / $averageBytes) - 1)];
    }
}
