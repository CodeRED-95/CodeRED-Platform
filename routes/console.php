<?php

use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Reniec\Enums\ReniecImportStatus;
use App\Modules\Reniec\Jobs\ProcessReniecImportJob;
use App\Modules\Reniec\Models\ReniecImport;
use App\Modules\Reniec\Services\ReniecCopyLoader;
use App\Modules\Reniec\Services\ReniecFileService;
use App\Modules\Reniec\Services\ReniecIncomingFileScanner;
use App\Modules\Reniec\Services\ReniecMergeService;
use App\Modules\Reniec\Support\ReniecLineParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command;

Artisan::command('health:redis', function (): int {
    $key = 'codered_health_test_'.bin2hex(random_bytes(8));
    $original = [
        'cache' => config('cache.default'),
        'queue' => config('queue.default'),
        'session' => config('session.driver'),
        'client' => config('database.redis.client'),
    ];

    try {
        config()->set([
            'cache.default' => 'redis',
            'queue.default' => 'redis',
            'session.driver' => 'redis',
            'database.redis.client' => 'phpredis',
        ]);

        Cache::put($key, 'ok', 60);
        $value = Cache::get($key);
        Cache::forget($key);

        if ($value !== 'ok') {
            $this->error('Redis o la caché no respondieron correctamente.');

            return Command::FAILURE;
        }

        Redis::connection()->ping();
    } catch (Throwable $e) {
        $this->error('PhpRedis no respondió: '.$e->getMessage());

        return Command::FAILURE;
    } finally {
        config()->set([
            'cache.default' => $original['cache'],
            'queue.default' => $original['queue'],
            'session.driver' => $original['session'],
            'database.redis.client' => $original['client'],
        ]);
    }

    $this->info('Redis y la caché responden correctamente.');

    return Command::SUCCESS;
})->purpose('Verifica Redis y la caché de Laravel sin Tinker.');

Artisan::command('auth:diagnose {--email=}', function (): int {
    $email = trim((string) $this->option('email'));
    $user = auth()->user();

    if (! $user && $email !== '') {
        $user = User::query()->where('email', $email)->with('roles.permissions')->first();
    }

    if (! $user) {
        $this->error('No se encontró un usuario para diagnosticar.');

        return Command::FAILURE;
    }

    $roles = $user->roles()->pluck('slug')->values()->all();
    $permissions = $user->roles()->with('permissions')->get()->flatMap(fn ($role) => $role->permissions->pluck('slug'))->unique()->values()->all();
    $agency = Agency::query()->first();

    $this->line('Email: '.(string) $user->getAttribute('email'));
    $this->line('Roles: '.json_encode($roles, JSON_UNESCAPED_UNICODE));
    $this->line('Permisos: '.json_encode($permissions, JSON_UNESCAPED_UNICODE));
    $this->line("hasRole('super-admin'): ".($user->hasRole('super-admin') ? 'true' : 'false'));
    $this->line("can('agencies.view'): ".($user->can('agencies.view') ? 'true' : 'false'));
    $this->line("Gate::allows('viewAny', Agency::class): ".(Gate::forUser($user)->allows('viewAny', Agency::class) ? 'true' : 'false'));
    $this->line("Gate::allows('create', Agency::class): ".(Gate::forUser($user)->allows('create', Agency::class) ? 'true' : 'false'));

    if ($agency) {
        $this->line("Gate::allows('view', Agency#{$agency->id}): ".(Gate::forUser($user)->allows('view', $agency) ? 'true' : 'false'));
    }

    return Command::SUCCESS;
})->purpose('Diagnostica roles y permisos del usuario autenticado o de un correo indicado.');

Artisan::command('agencies:prune-sync-changes {--dry-run} {--days=}', function (): int {
    $configuredDays = (int) config('api.agency_changelog_retention_days');
    $days = $this->option('days') !== null ? (int) $this->option('days') : $configuredDays;
    if ($days < 1) {
        $this->error('El periodo de retención debe ser de al menos un día.');

        return Command::INVALID;
    }

    $cutoff = now()->subDays($days);
    $query = DB::table('agency_sync_changes')->where('changed_at', '<', $cutoff);
    $count = (clone $query)->count();
    $maximumPrunedSequence = (int) ((clone $query)->max('id') ?? 0);
    if ($this->option('dry-run')) {
        $this->info($count.' cambios vencerían antes de '.$cutoff->toIso8601String().'. No se eliminó ninguno.');

        return Command::SUCCESS;
    }

    DB::transaction(function () use ($query, $maximumPrunedSequence): void {
        $query->delete();
        if ($maximumPrunedSequence > 0) {
            DB::table('agency_sync_states')->where('id', 1)->update([
                'minimum_sequence' => $maximumPrunedSequence,
                'updated_at' => now(),
            ]);
        }
    });
    $this->info($count.' cambios vencidos fueron eliminados.');

    return Command::SUCCESS;
})->purpose('Elimina cambios incrementales vencidos conservando el watermark de cursores.');

Schedule::command('agencies:prune-sync-changes')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('reniec:scan', function (): int {
    $scanner = app(ReniecIncomingFileScanner::class);
    $diagnostics = $scanner->diagnostics();
    $this->line('Disk: '.$diagnostics['disk']);
    $this->line('Directorio configurado: '.$diagnostics['configured_directory']);
    $this->line('Ruta física: '.$diagnostics['physical_path']);
    $this->line('Directorio existe: '.($diagnostics['exists'] ? 'sí' : 'no'));
    $files = $scanner->scan();
    $this->table(['Nombre', 'Tamaño', 'Fecha', 'Estado'], collect($files)->map(fn (array $file): array => [$file['name'], $file['size'], date('Y-m-d H:i:s', $file['last_modified']), $file['status']])->all());
    $this->info(count($files).' archivos TXT encontrados.');

    return Command::SUCCESS;
})->purpose('Diagnostica y lista archivos RENIEC disponibles en el servidor.');
Artisan::command('reniec:register {path} {--dry-run} {--strategy=}', function (): int {
    if ($this->option('dry-run')) {
        $this->info('Validación seca: '.$this->argument('path'));

        return Command::SUCCESS;
    }
    $import = app(ReniecFileService::class)->register((string) $this->argument('path'), null, $this->option('strategy') ?: null);
    $this->info('Importación registrada: '.$import->id.' ('.$import->uuid.')');

    return Command::SUCCESS;
})->purpose('Registra sin copiar un archivo RENIEC ya ubicado en el servidor.');

Artisan::command('reniec:import {import_id}', function (): int {
    $import = ReniecImport::query()->findOrFail($this->argument('import_id'));
    $import->update(['status' => ReniecImportStatus::Queued]);
    ProcessReniecImportJob::dispatch($import->id);
    $this->info('Importación enviada a reniec-imports.');

    return Command::SUCCESS;
});

Artisan::command('reniec:resume {import_id}', function (): int {
    $import = ReniecImport::query()->findOrFail($this->argument('import_id'));
    app(ReniecFileService::class)->assertUnchanged($import);
    $import->update(['status' => ReniecImportStatus::Queued, 'paused_at' => null, 'cancel_requested_at' => null, 'resumed_at' => now(), 'error_message' => null]);
    ProcessReniecImportJob::dispatch($import->id);
    $this->info('Reanudación en cola.');

    return Command::SUCCESS;
});

Artisan::command('reniec:pause {import_id}', function (): int {
    ReniecImport::query()->findOrFail($this->argument('import_id'))->update(['paused_at' => now()]);
    $this->info('Pausa solicitada; se aplicará tras el lote actual.');

    return Command::SUCCESS;
});
Artisan::command('reniec:cancel {import_id}', function (): int {
    ReniecImport::query()->findOrFail($this->argument('import_id'))->update(['cancel_requested_at' => now(), 'status' => ReniecImportStatus::Cancelling]);
    $this->info('Cancelación solicitada.');

    return Command::SUCCESS;
});
Artisan::command('reniec:status {--id=}', function (): int {
    $q = ReniecImport::query();
    if ($this->option('id')) {
        $q->whereKey($this->option('id'));
    }$this->table(['ID', 'Archivo', 'Estado', 'Línea', 'Offset', 'Válidas', 'Inválidas', 'Heartbeat'], $q->latest()->limit(50)->get()->map(fn ($i) => [$i->id, $i->original_filename, $i->status->value, $i->current_line_number, $i->current_byte_offset, $i->valid_rows, $i->invalid_rows, $i->last_heartbeat_at])->all());

    return Command::SUCCESS;
});
Artisan::command('reniec:cleanup {--dry-run}', function (): int {
    $q = ReniecImport::query()->where('finished_at', '<', now()->subDays(config('reniec.import.retention_days')));
    $count = $q->count();
    if (! $this->option('dry-run')) {
        $q->each(fn ($i) => $i->delete());
    }$this->info($count.' importaciones vencidas '.($this->option('dry-run') ? 'detectadas' : 'eliminadas').'.');

    return Command::SUCCESS;
});
Artisan::command('reniec:validate-file {path}', function (): int {
    $import = app(ReniecFileService::class)->register((string) $this->argument('path'));
    $import->delete();
    $this->info('Archivo, espacio, tamaño y checksum válidos.');

    return Command::SUCCESS;
});
Artisan::command('reniec:analyze', function (): int {
    if (DB::getDriverName() === 'pgsql') {
        DB::statement('ANALYZE dni_records');
    }$this->info('ANALYZE completado.');

    return Command::SUCCESS;
});

Schedule::command('reniec:cleanup')->dailyAt('03:15')->withoutOverlapping();

Artisan::command('reniec:benchmark {--rows=1000000} {--strategy=insert_ignore}', function (): int {
    $rows = max(1, (int) $this->option('rows'));
    $strategy = (string) $this->option('strategy');
    if (! in_array($strategy, ['insert_ignore', 'upsert'], true)) {
        $this->error('Estrategia inválida.');

        return Command::INVALID;
    }
    $disk = Storage::disk(config('reniec.import.disk'));
    $directory = app(ReniecIncomingFileScanner::class)->storageDirectory($disk);
    $path = $directory.'/benchmark-'.now()->format('YmdHis').'.txt';
    $stream = fopen('php://temp/maxmemory:1048576', 'w+b');
    fwrite($stream, "DNI|NOMBRES|PATERNO|MATERNO|FECHA|SEXO|UBIGEO|\n");
    for ($i = 1; $i <= $rows; $i++) {
        fwrite($stream, sprintf("%08d|NOMBRE%d|PATERNO|MATERNO|1990-01-01|X|150101|\n", $i % 100000000, $i));
        if (ftell($stream) > 900000) {
            rewind($stream);
            $disk->append($path, stream_get_contents($stream));
            ftruncate($stream, 0);
            rewind($stream);
        }
    }rewind($stream);
    $tail = stream_get_contents($stream);
    if ($tail !== '') {
        $disk->append($path, $tail);
    }fclose($stream);
    $start = microtime(true);
    $import = app(ReniecFileService::class)->register($path, null, $strategy);
    (new ProcessReniecImportJob($import->id))->handle(app(ReniecFileService::class), app(ReniecLineParser::class), app(ReniecCopyLoader::class), app(ReniecMergeService::class));
    $seconds = microtime(true) - $start;
    $this->table(['Filas', 'Segundos', 'Filas/s', 'Memoria pico'], [[$rows, round($seconds, 2), round($rows / max(.001, $seconds), 2), memory_get_peak_usage(true)]]);

    return Command::SUCCESS;
})->purpose('Benchmark opcional del pipeline RENIEC con datos sintéticos locales.');

Artisan::command('reniec:detect-stalled', function (): int {
    $count = ReniecImport::query()->whereIn('status', array_map(fn ($s) => $s->value, array_filter(ReniecImportStatus::cases(), fn ($s) => $s->active())))->where(fn ($q) => $q->whereNull('last_heartbeat_at')->where('updated_at', '<', now()->subMinutes(15))->orWhere('last_heartbeat_at', '<', now()->subMinutes(15)))->update(['status' => ReniecImportStatus::Stalled->value, 'error_message' => 'El worker no actualizó el heartbeat durante 15 minutos.', 'updated_at' => now()]);
    $this->info($count.' importaciones RENIEC marcadas como detenidas.');

    return Command::SUCCESS;
});
Schedule::command('reniec:detect-stalled')->everyTenMinutes()->withoutOverlapping();
