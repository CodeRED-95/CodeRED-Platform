<?php

namespace App\Console\Commands;

use App\Models\DniRecord;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportLegacyDni extends Command
{
    protected $signature = 'dni:import-legacy
        {--connection=legacy_dni : Conexión configurada para dni-api}
        {--table=dni_consultas : Tabla de origen}
        {--chunk=500 : Registros por transacción}
        {--dry-run : Validar y contar sin escribir}';

    protected $description = 'Importa registros de dni-api sin borrar ni sobrescribir datos existentes';

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $table = (string) $this->option('table');
        $chunk = max(1, min((int) $this->option('chunk'), 5000));

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1 || ! Schema::connection($connection)->hasTable($table)) {
            $this->error('La tabla de origen no existe o su nombre no es válido.');

            return self::FAILURE;
        }

        $processed = 0;
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $dryRun = (bool) $this->option('dry-run');

        DB::connection($connection)->table($table)->orderBy('id')->chunkById($chunk, function ($rows) use (&$processed, &$imported, &$skipped, &$errors, $dryRun): void {
            DB::transaction(function () use ($rows, &$processed, &$imported, &$skipped, &$errors, $dryRun): void {
                foreach ($rows as $row) {
                    $processed++;
                    try {
                        $dni = trim((string) ($row->dni ?? ''));
                        if (preg_match('/^\d{8}$/', $dni) !== 1 || DniRecord::query()->where('dni', $dni)->exists()) {
                            $skipped++;

                            continue;
                        }

                        $attributes = [
                            'dni' => $dni,
                            'nombres' => trim((string) ($row->nombres ?? '')),
                            'apellido_paterno' => trim((string) ($row->apellido_paterno ?? '')),
                            'apellido_materno' => trim((string) ($row->apellido_materno ?? '')),
                            'nombre_completo' => trim((string) ($row->nombre_completo ?? '')),
                            'genero' => $this->nullableString($row->genero ?? null),
                            'fecha_nacimiento' => $this->normalizeDate($row->fecha_nacimiento ?? null),
                            'codigo_verificacion' => $this->nullableString($row->codigo_verificacion ?? null),
                            'provider_reference' => $this->nullableString($row->perudevs_id ?? null),
                            'source' => 'import',
                            'last_verified_at' => $row->fecha_actualizacion ?? $row->fecha_consulta ?? null,
                        ];

                        if ($attributes['nombres'] === '' || $attributes['apellido_paterno'] === '' || $attributes['apellido_materno'] === '' || $attributes['nombre_completo'] === '') {
                            $errors++;

                            continue;
                        }
                        if (! $dryRun) {
                            DniRecord::query()->create($attributes);
                        }
                        $imported++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $errors++;
                    }
                }
            });
        }, 'id');

        $this->table(['Procesados', 'Importables', 'Omitidos', 'Errores', 'Modo'], [[$processed, $imported, $skipped, $errors, $dryRun ? 'dry-run' : 'escritura']]);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        foreach (['!d/m/Y', '!Y-m-d'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, trim($value))->format('Y-m-d');
            } catch (Throwable) {
                // Probar el siguiente formato legado.
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
