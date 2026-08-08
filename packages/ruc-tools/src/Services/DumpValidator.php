<?php

namespace RucTool\Services;

/**
 * Verifica que un archivo sea un dump custom de pg_dump válido y que su TOC
 * (`pg_restore --list`) pertenezca exclusivamente a ruc_records: la misma
 * tabla, su secuencia, y cualquier índice/constraint propio (siempre
 * nombrados con el prefijo de la tabla por convención de PostgreSQL).
 *
 * Puerto directo de la validación equivalente en
 * App\Modules\Ruc\Services\RucBackupService::assertDumpBelongsToRucRecords
 * de CodeRED-Platform (mismo bug corregido ahí: "SEQUENCE OWNED BY" es un
 * tipo de TOC de dos palabras que, si no se agrega antes que "SEQUENCE" en
 * la alternancia, hace match parcial y desplaza qué texto cae en cada grupo
 * capturado).
 */
class DumpValidator
{
    private const EXPECTED_TABLE = 'ruc_records';

    private const ALLOWED_TOC_PATTERN = '/^ruc_records(_.*)?$/';

    /**
     * @throws \Exception si el archivo no es un dump válido o contiene
     *                     objetos ajenos a ruc_records.
     */
    public function assertBelongsToRucRecords(string $filePath): void
    {
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            throw new \Exception('El archivo no existe o está vacío.');
        }

        $command = sprintf('pg_restore --list %s 2>&1', escapeshellarg($filePath));
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception(
                'El archivo no es un dump válido de PostgreSQL (formato custom de pg_dump). ' . implode("\n", $output)
            );
        }

        $sawExpectedTable = false;

        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';')) {
                continue;
            }

            // Formato real: "3243; 1259 24592 TABLE public ruc_records codered"
            // Orden importa: alternativas más largas/específicas primero
            // ("SEQUENCE OWNED BY" antes que "SEQUENCE"), o la alternancia
            // hace match parcial y desplaza los grupos capturados.
            if (!preg_match(
                '/^\d+;\s+\d+\s+\d+\s+(TABLE DATA|SEQUENCE OWNED BY|SEQUENCE SET|SEQUENCE|TABLE|CONSTRAINT|INDEX|DEFAULT)\s+(\S+)\s+(\S+)/',
                $line,
                $m
            )) {
                continue;
            }

            $objectName = $m[3];

            if (!preg_match(self::ALLOWED_TOC_PATTERN, $objectName)) {
                throw new \Exception(
                    "El archivo contiene el objeto \"{$objectName}\", ajeno a ruc_records. " .
                    'Por seguridad solo se aceptan dumps que contengan exclusivamente la tabla ruc_records.'
                );
            }

            if ($objectName === self::EXPECTED_TABLE) {
                $sawExpectedTable = true;
            }
        }

        if (!$sawExpectedTable) {
            throw new \Exception('El archivo no contiene la tabla "ruc_records" esperada.');
        }
    }
}
