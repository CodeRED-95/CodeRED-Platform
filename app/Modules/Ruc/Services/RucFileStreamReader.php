<?php

namespace App\Modules\Ruc\Services;

use RuntimeException;
use SplFileObject;

class RucFileStreamReader
{
    /**
     * Abre un stream del archivo
     */
    public function open(string $path): SplFileObject
    {
        if (!file_exists($path)) {
            throw new RuntimeException("El archivo no existe: {$path}");
        }

        $file = new SplFileObject($path, 'r');
        if (!is_resource($file->getRealPath())) {
            throw new RuntimeException("No se pudo abrir el archivo: {$path}");
        }

        return $file;
    }

    /**
     * Lee la siguiente línea del archivo
     * Retorna null si EOF
     */
    public function readline(SplFileObject $file): ?string
    {
        if ($file->eof()) {
            return null;
        }

        $line = $file->fgets();
        return $line !== false ? $line : null;
    }

    /**
     * Salta a un byte offset específico (para reanudación)
     */
    public function seekToOffset(SplFileObject $file, int $offset): bool
    {
        return $file->fseek($offset) === 0;
    }

    /**
     * Obtiene el byte offset actual
     */
    public function currentOffset(SplFileObject $file): int
    {
        $pos = $file->ftell();
        return $pos !== false ? $pos : -1;
    }

    /**
     * Obtiene el número de línea actual
     */
    public function currentLine(SplFileObject $file): int
    {
        return $file->key() ?? 0;
    }

    /**
     * Cierra el stream
     */
    public function close(SplFileObject $file): void
    {
        // SplFileObject se cierra automáticamente
        unset($file);
    }

    /**
     * Valida que el archivo no cambió
     */
    public function validateChecksum(string $path, string $expected): bool
    {
        if (!file_exists($path)) {
            throw new RuntimeException("El archivo no existe: {$path}");
        }

        $actual = hash_file('sha256', $path);
        if ($actual === false) {
            throw new RuntimeException("No se pudo calcular checksum del archivo: {$path}");
        }

        return strtolower($actual) === strtolower($expected);
    }

    /**
     * Cuenta las líneas del archivo de forma eficiente
     * Usa wc -l del sistema operativo si está disponible
     */
    public function countLines(string $path): int
    {
        if (!file_exists($path)) {
            throw new RuntimeException("El archivo no existe: {$path}");
        }

        // Intenta usar wc -l (Unix/Linux/MacOS)
        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            $output = [];
            $returnVar = 0;
            exec("wc -l < " . escapeshellarg($path), $output, $returnVar);

            if ($returnVar === 0 && !empty($output[0])) {
                return (int) trim($output[0]);
            }
        }

        // Fallback: contar líneas con streaming (O(1) memoria)
        $count = 0;
        $file = $this->open($path);

        try {
            while (!$file->eof()) {
                $file->fgets();
                $count++;
            }
        } finally {
            $this->close($file);
        }

        return $count;
    }

    /**
     * Obtiene el tamaño del archivo en bytes
     */
    public function fileSize(string $path): int
    {
        if (!file_exists($path)) {
            throw new RuntimeException("El archivo no existe: {$path}");
        }

        $size = filesize($path);
        if ($size === false) {
            throw new RuntimeException("No se pudo obtener tamaño del archivo: {$path}");
        }

        return $size;
    }

    /**
     * Detecta el encoding del archivo
     */
    public function detectEncoding(SplFileObject $file): string
    {
        $file->rewind();

        // Lee primeras líneas para detectar encoding
        for ($i = 0; $i < 10 && !$file->eof(); $i++) {
            $line = $file->fgets();
            if ($line === false) {
                break;
            }

            // Detecta UTF-8 con BOM
            if (str_starts_with($line, "\xEF\xBB\xBF")) {
                return 'UTF-8';
            }

            // Detecta si es UTF-8 válido
            if (mb_check_encoding($line, 'UTF-8')) {
                return 'UTF-8';
            }

            // Detecta ISO-8859-1
            if (mb_check_encoding($line, 'ISO-8859-1')) {
                return 'ISO-8859-1';
            }

            // Detecta Windows-1252
            if (mb_check_encoding($line, 'Windows-1252')) {
                return 'Windows-1252';
            }
        }

        $file->rewind();
        return 'UTF-8'; // Default
    }
}
