<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Services;

use Symfony\Component\Process\Process;

/**
 * Única puerta por la que backup y restore lanzan procesos externos
 * (psql/zstd).
 *
 * Existe para que los tests puedan sustituirla y ejercitar los caminos de
 * error —lote que falla a mitad, chunk corrupto, cancelación— sin necesidad
 * de provocar fallos reales en PostgreSQL, que serían lentos y difíciles de
 * reproducir de forma determinista. En producción es una envoltura mínima.
 */
class RucBackupProcessRunner
{
    public function run(Process $process): void
    {
        $process->run();
    }
}
