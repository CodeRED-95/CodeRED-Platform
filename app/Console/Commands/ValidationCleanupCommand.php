<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Declaration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Borra los registros creados por una validación de extremo a extremo.
 *
 * Existe por un incidente concreto: el 16/08/2026 una limpieza manual con
 * `DELETE ... WHERE id BETWEEN 10 AND 20` se llevó por delante una declaración
 * real que había caído dentro del rango. El rango era una conjetura sobre qué
 * identificadores pertenecían a la prueba, y las conjeturas sobre identificadores
 * no distinguen los datos propios de los ajenos.
 *
 * Este comando no acepta identificadores ni rangos: sólo el UUID de una
 * ejecución de validación. Un registro que no lleve ese UUID no puede ser
 * alcanzado por él, y los que lo llevan sólo pueden haberlo recibido al crearse
 * expresamente como parte de esa validación.
 *
 * Por defecto no borra nada: enumera lo que borraría y termina.
 */
class ValidationCleanupCommand extends Command
{
    protected $signature = 'validation:cleanup
        {run : UUID de la ejecución de validación}
        {--force : Borra de verdad. Sin esta opción sólo se enumera}';

    protected $description = 'Borra los registros de una validación E2E, identificados por su UUID de ejecución';

    public function handle(): int
    {
        $run = (string) $this->argument('run');

        // Un UUID mal formado no puede ser el resultado de una validación real,
        // y aceptarlo abriría la puerta a que cualquier cadena seleccionara
        // filas por accidente.
        if (! Str::isUuid($run)) {
            $this->error('El identificador de ejecución debe ser un UUID.');
            $this->line('Uso: php artisan validation:cleanup 4f1c…  [--force]');

            return self::FAILURE;
        }

        $declarations = Declaration::withTrashed()
            ->where('validation_run', $run)
            ->with('items')
            ->orderBy('id')
            ->get();

        if ($declarations->isEmpty()) {
            $this->info('No hay registros de esa ejecución de validación. No se toca nada.');

            return self::SUCCESS;
        }

        $this->line(sprintf('Ejecución %s: %d declaración(es).', $run, $declarations->count()));
        $this->newLine();

        $this->table(
            ['id', 'remitente', 'bienes', 'PDF', 'foto'],
            $declarations->map(fn (Declaration $d): array => [
                $d->getKey(),
                (string) $d->remitente_nombre,
                $d->items->count(),
                $d->pdf_path === null ? '—' : 'sí',
                $d->foto_dni_path === null ? '—' : 'sí',
            ])->all()
        );

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Simulación: no se ha borrado nada.');
            $this->line('Repite con --force para borrarlo.');

            return self::SUCCESS;
        }

        // --force ya es la confirmacion explicita. La pregunta adicional solo
        // tiene sentido delante de una persona: `isInteractive()` refleja la
        // opcion --no-interaction, no si hay terminal, y sin comprobar esto el
        // comando se queda colgado -o se cancela solo- en cualquier script.
        if (defined('STDIN') && stream_isatty(STDIN)
            && ! $this->confirm(sprintf('¿Borrar definitivamente estas %d declaraciones?', $declarations->count()), false)) {
            $this->info('Cancelado. No se ha borrado nada.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        $borradas = 0;

        foreach ($declarations as $declaration) {
            // Los archivos primero: si algo falla a mitad, es preferible una
            // fila sin archivos que archivos sin fila a la que pertenecen.
            foreach ([$declaration->pdf_path, $declaration->foto_dni_path] as $ruta) {
                if (is_string($ruta) && $ruta !== '' && $disk->exists($ruta)) {
                    $disk->delete($ruta);
                }
            }

            // forceDelete y no delete: estos registros son residuo de una
            // prueba, no documentos que alguien pueda necesitar recuperar. El
            // borrado reversible protege a los reales, no a estos.
            $declaration->items()->delete();
            $declaration->forceDelete();

            $borradas++;
        }

        $this->newLine();
        $this->info(sprintf('%d declaración(es) de validación borradas.', $borradas));

        return self::SUCCESS;
    }
}
