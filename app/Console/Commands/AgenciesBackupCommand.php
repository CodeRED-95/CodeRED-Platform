<?php

namespace App\Console\Commands;

use App\Modules\Agencies\Services\AgencyBackupService;
use Illuminate\Console\Command;
use Throwable;

class AgenciesBackupCommand extends Command
{
    protected $signature = 'agencies:backup {--disk=local} {--output=} {--no-cleanup}';

    protected $description = 'Crea una copia privada y recuperable de todas las agencias';

    public function handle(AgencyBackupService $service): int
    {
        try {
            $backup = $service->create(null, (string) $this->option('disk'), $this->option('output') ?: null, ! $this->option('no-cleanup'));
            $this->info('Copia completada.');
            $this->line('Registros: '.$backup->record_count);
            $this->line('Archivo: '.$backup->path);
            $this->line('Tamaño: '.$backup->size_bytes.' bytes');
            $this->line('SHA-256: '.$backup->checksum_sha256);

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('No fue posible crear la copia de seguridad. Revisa el registro seguro de la aplicación.');

            return self::FAILURE;
        }
    }
}
