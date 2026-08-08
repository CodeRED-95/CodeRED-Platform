<?php

namespace RucTool\Services;

/**
 * División y reconstrucción binaria de archivos grandes, en streaming
 * (O(1) memoria respecto al tamaño total: nunca file_get_contents()).
 *
 * UN solo archivo de entrada se divide en partes de tamaño fijo — no hay
 * ninguna noción de "varios pg_dump"; este servicio no sabe nada de
 * PostgreSQL, solo corta/pega bytes.
 */
class BackupPartitioner
{
    /** Búfer de lectura/escritura, independiente del tamaño de cada parte. */
    private const CHUNK_SIZE = 8 * 1024 * 1024; // 8 MiB

    /**
     * Divide $sourcePath en partes de exactamente $partSizeBytes (la última
     * puede ser menor). Nombres con zero-padding de 4 dígitos: part0001,
     * part0002... (ordenables lexicográficamente).
     *
     * @return array<int, array{index:int, filename:string, size_bytes:int, sha256:string}>
     */
    public function split(string $sourcePath, string $outputDir, string $sourceBasename, int $partSizeBytes): array
    {
        if ($partSizeBytes <= 0) {
            throw new \InvalidArgumentException('part_size_bytes debe ser mayor que 0.');
        }

        $source = fopen($sourcePath, 'rb');
        if ($source === false) {
            throw new \Exception("No se pudo abrir el archivo de origen: {$sourcePath}");
        }

        $parts = [];
        $partIndex = 0;

        try {
            while (!feof($source)) {
                $partIndex++;
                $partFilename = sprintf('%s.part%04d', $sourceBasename, $partIndex);
                $partPath = "{$outputDir}/{$partFilename}";

                $dest = fopen($partPath, 'wb');
                if ($dest === false) {
                    throw new \Exception("No se pudo crear la parte: {$partFilename}");
                }

                $bytesWritten = 0;
                $hashContext = hash_init('sha256');

                try {
                    while ($bytesWritten < $partSizeBytes && !feof($source)) {
                        $toRead = min(self::CHUNK_SIZE, $partSizeBytes - $bytesWritten);
                        $chunk = fread($source, $toRead);
                        if ($chunk === false) {
                            throw new \Exception('Error leyendo el archivo de origen.');
                        }
                        if ($chunk === '') {
                            break;
                        }

                        $written = fwrite($dest, $chunk);
                        if ($written === false || $written !== strlen($chunk)) {
                            throw new \Exception("Error escribiendo {$partFilename} (¿disco lleno?).");
                        }

                        hash_update($hashContext, $chunk);
                        $bytesWritten += strlen($chunk);
                    }
                } finally {
                    fclose($dest);
                }

                if ($bytesWritten === 0) {
                    // Archivo vacío o tamaño exactamente múltiplo del part-size:
                    // no dejar una parte fantasma de 0 bytes.
                    @unlink($partPath);
                    $partIndex--;
                    break;
                }

                $parts[] = [
                    'index' => $partIndex,
                    'filename' => $partFilename,
                    'size_bytes' => $bytesWritten,
                    'sha256' => hash_final($hashContext),
                ];
            }
        } finally {
            fclose($source);
        }

        if (empty($parts)) {
            throw new \Exception('El archivo de origen está vacío; no se generó ninguna parte.');
        }

        return $parts;
    }

    /**
     * Reconstruye el archivo original concatenando las partes en orden, por
     * streaming (stream_copy_to_stream nunca carga el archivo completo).
     *
     * @param string[] $partPaths Rutas absolutas, ya en el orden correcto.
     */
    public function join(array $partPaths, string $outputPath): void
    {
        $dest = fopen($outputPath, 'wb');
        if ($dest === false) {
            throw new \Exception("No se pudo crear el archivo de salida: {$outputPath}");
        }

        try {
            foreach ($partPaths as $partPath) {
                if (!file_exists($partPath)) {
                    throw new \Exception('Falta la parte: ' . basename($partPath));
                }

                $source = fopen($partPath, 'rb');
                if ($source === false) {
                    throw new \Exception('No se pudo abrir la parte: ' . basename($partPath));
                }

                try {
                    if (stream_copy_to_stream($source, $dest) === false) {
                        throw new \Exception('Error copiando la parte: ' . basename($partPath));
                    }
                } finally {
                    fclose($source);
                }
            }
        } finally {
            fclose($dest);
        }
    }

    /**
     * SHA-256 del contenido combinado de todas las partes, SIN escribir un
     * archivo unido — recorre cada parte por streaming y va actualizando un
     * único contexto de hash. Usado para verificar un split/manifest sin
     * duplicar espacio en disco.
     *
     * @param string[] $partPaths Rutas absolutas, en el orden correcto.
     */
    public function streamingSha256(array $partPaths): string
    {
        $context = hash_init('sha256');

        foreach ($partPaths as $partPath) {
            if (!file_exists($partPath)) {
                throw new \Exception('Falta la parte: ' . basename($partPath));
            }

            $handle = fopen($partPath, 'rb');
            if ($handle === false) {
                throw new \Exception('No se pudo abrir la parte: ' . basename($partPath));
            }

            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, self::CHUNK_SIZE);
                    if ($chunk === false) {
                        throw new \Exception('Error leyendo la parte: ' . basename($partPath));
                    }
                    if ($chunk !== '') {
                        hash_update($context, $chunk);
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        return hash_final($context);
    }
}
