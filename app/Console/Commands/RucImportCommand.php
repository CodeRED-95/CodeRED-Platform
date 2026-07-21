<?php

namespace App\Console\Commands;

use App\Modules\Ruc\Jobs\ProcessRucImportJob;
use App\Modules\Ruc\Services\RucImportService;
use App\Modules\Ruc\Support\RucPadronParser;
use Illuminate\Console\Command;

class RucImportCommand extends Command
{
    protected $signature = 'ruc:import {file} {--sync} {--force} {--encoding=} {--delimiter=}';

    protected $description = 'Almacena y encola una importación privada del padrón reducido SUNAT';

    public function handle(RucImportService $service): int
    {
        if ($this->option('encoding')) {
            config()->set('ruc.import_encoding', $this->option('encoding'));
        }
        if ($this->option('delimiter')) {
            config()->set('ruc.import_delimiter', $this->option('delimiter'));
        }
        $sync = (bool) $this->option('sync');
        $import = $service->fromPath((string) $this->argument('file'), null, (bool) $this->option('force'), ! $sync);
        if ($sync) {
            app(ProcessRucImportJob::class, ['importId' => $import->id])->handle(app(RucPadronParser::class));
        }
        $this->info('Importación registrada: '.$import->uuid);
        $this->line('Estado: '.$import->fresh()->status->value);

        return self::SUCCESS;
    }
}
