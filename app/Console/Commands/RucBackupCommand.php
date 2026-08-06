<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class RucBackupCommand extends Command
{
    protected $signature = 'ruc:backup {--type=full : Tipo de backup (full, incremental, manual)} {--user= : Usuario que realiza el backup}';

    protected $description = 'Crear backup de la tabla ruc_records y subirlo a S3';

    public function handle(): int
    {
        $type = $this->option('type');
        $userId = $this->option('user');

        $this->info("Iniciando backup RUC (tipo: {$type})...");

        try {
            $user = $userId ? \App\Models\User::find($userId) : null;

            $backup = (new RucBackupService())->backup($type, $user);

            $this->newLine();
            $this->info('✅ Backup completado exitosamente');
            $this->table(
                ['Propiedad', 'Valor'],
                [
                    ['Backup ID', $backup->id],
                    ['Nombre', $backup->name],
                    ['Registros', number_format($backup->total_records ?? 0)],
                    ['Tamaño', $backup->getFormattedSize()],
                    ['Almacenamiento', $backup->storage_type],
                    ['Duración', $backup->duration_seconds . ' segundos'],
                    ['Checksum', substr($backup->checksum_sha256 ?? 'N/A', 0, 16) . '...'],
                ]
            );

            return 0;

        } catch (\Throwable $e) {
            $this->error('❌ Backup fallido: ' . $e->getMessage());
            return 1;
        }
    }
}
