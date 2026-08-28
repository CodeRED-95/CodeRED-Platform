<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Declaration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Copia recuperable de las declaraciones juradas y sus documentos.
 *
 * El sistema de backup existente cubre el padrón RUC y el catálogo de agencias
 * —los dos volúmenes grandes—, pero no las declaraciones, que son pocas y
 * precisamente por eso pasaron desapercibidas. Cuando el 16/08/2026 se borró
 * una por error, no había nada de donde recuperarla.
 *
 * No hace falta el aparato del backup de RUC: aquí hablamos de decenas o
 * centenares de filas, así que un ZIP con un JSON y los archivos es suficiente,
 * legible y restaurable sin herramientas.
 *
 * El archivo contiene datos personales y documentos de identidad: se escribe en
 * el disco privado, nunca en `public`.
 */
class DeclarationsBackupCommand extends Command
{
    protected $signature = 'declarations:backup
        {--output= : Ruta relativa dentro del disco privado}';

    protected $description = 'Crea una copia privada y recuperable de las declaraciones juradas, sus bienes y sus PDFs';

    public function handle(): int
    {
        $disk = Storage::disk('local');

        $relative = (string) ($this->option('output')
            ?: sprintf('backups/declarations/declaraciones-%s.zip', now()->format('Ymd-His')));

        $disk->makeDirectory(dirname($relative));
        $absolute = $disk->path($relative);

        $declarations = Declaration::withTrashed()->with('items')->orderBy('id')->get();

        $zip = new ZipArchive;

        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de copia: '.$relative);
        }

        $manifest = [
            'generado_en' => now()->toIso8601String(),
            'total' => $declarations->count(),
            'declaraciones' => [],
        ];

        $archivos = 0;

        foreach ($declarations as $declaration) {
            $fila = $declaration->attributesToArray();
            $fila['items'] = $declaration->items->map->attributesToArray()->all();

            // Los archivos van dentro del ZIP, y el manifiesto guarda el nombre
            // con el que entraron: así la restauración no depende de que las
            // rutas del disco sigan siendo las mismas.
            foreach (['pdf_path' => 'pdf', 'foto_dni_path' => 'foto'] as $campo => $etiqueta) {
                $ruta = $declaration->{$campo};

                if (! is_string($ruta) || $ruta === '' || ! $disk->exists($ruta)) {
                    continue;
                }

                $dentro = sprintf('archivos/%d/%s-%s', $declaration->getKey(), $etiqueta, basename($ruta));
                $zip->addFromString($dentro, (string) $disk->get($ruta));

                $fila['_archivos'][$etiqueta] = $dentro;
                $archivos++;
            }

            $manifest['declaraciones'][] = $fila;
        }

        $zip->addFromString(
            'declaraciones.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $zip->close();

        $this->info(sprintf(
            'Copia creada: %s (%d declaraciones, %d archivos, %s).',
            $relative,
            $declarations->count(),
            $archivos,
            $this->humanSize((int) $disk->size($relative))
        ));

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unidad) {
            if ($bytes < 1024) {
                return sprintf('%.1f %s', $bytes, $unidad);
            }

            $bytes = intdiv($bytes, 1024);
        }

        return $bytes.' TB';
    }
}
