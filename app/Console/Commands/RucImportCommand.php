<?php

namespace App\Console\Commands;

use App\Modules\Ruc\Services\RucImportService;
use Illuminate\Console\Command;

class RucImportCommand extends Command
{
    protected $signature = 'ruc:register-file {path} {--force}';

    protected $description = 'Registra un TXT SUNAT ya colocado en private/ruc/incoming';

    public function handle(RucImportService $service): int
    {
        $import = $service->registerServerFile((string) $this->argument('path'), null, (bool) $this->option('force'));
        $this->info('Importación RUC registrada: '.$import->id.' · '.$import->uuid);

        return self::SUCCESS;
    }
}
