<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Models\RucRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RucRecalculateMetricsCommand extends Command
{
    protected $signature = 'ruc:recalculate-metrics';

    protected $description = 'Invalida y muestra las métricas operativas RUC';

    public function handle(): int
    {
        Cache::forget('dashboard:ruc');
        $this->table(['Registros', 'Importaciones', 'Consultas hoy'], [[
            RucRecord::query()->count(),
            RucImport::query()->count(),
            ApiRequestLog::query()->where('service', 'ruc')->whereDate('created_at', today())->count(),
        ]]);

        return self::SUCCESS;
    }
}
