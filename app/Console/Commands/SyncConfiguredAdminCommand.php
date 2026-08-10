<?php

namespace App\Console\Commands;

use App\Services\Auth\ConfiguredAdminSyncService;
use Illuminate\Console\Command;

class SyncConfiguredAdminCommand extends Command
{
    protected $signature = 'app:sync-configured-admin';

    protected $description = 'Crea o sincroniza el administrador configurado en .env.';

    public function handle(ConfiguredAdminSyncService $sync): int
    {
        $result = $sync->sync();

        $this->line($result['created'] ? 'Administrador creado correctamente.' : 'Administrador sincronizado correctamente.');

        return self::SUCCESS;
    }
}
