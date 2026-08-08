<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Console\Command;

class RucBackupCommand extends Command
{
    protected $signature = 'ruc:backup {--user= : ID del usuario que realiza el backup}';

    protected $description = 'Crear un backup (solo datos) de la tabla ruc_records';

    public function handle(): int
    {
        $userId = $this->option('user');

        $this->info('Iniciando backup de ruc_records...');

        try {
            $user = $userId ? User::find($userId) : null;

            $backup = app(RucBackupService::class)->create($user);

            $this->newLine();
            $this->info('✅ Backup completado exitosamente');
            $this->table(
                ['Propiedad', 'Valor'],
                [
                    ['Backup ID', $backup->id],
                    ['Nombre', $backup->name],
                    ['Registros', number_format($backup->total_records ?? 0)],
                    ['Tamaño', $backup->formattedSize()],
                    ['Checksum', substr($backup->checksum_sha256 ?? 'N/D', 0, 16).'...'],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Backup fallido: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
