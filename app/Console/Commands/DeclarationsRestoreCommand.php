<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Declaration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Devuelve declaraciones desde una copia creada por `declarations:backup`.
 *
 * Restaurar es la mitad que de verdad importa de una copia de seguridad, así
 * que existe desde el principio y no "cuando haga falta": una copia que nadie
 * ha probado a restaurar no es una copia, es un archivo.
 *
 * Nunca pisa una declaración que ya exista. Si el identificador está ocupado
 * —por la misma o por otra distinta— se salta y lo dice: recuperar un documento
 * perdido no puede llevarse por delante uno vivo.
 */
class DeclarationsRestoreCommand extends Command
{
    protected $signature = 'declarations:restore
        {archive : Ruta del ZIP dentro del disco privado}
        {--id=* : Restaurar sólo estos identificadores}
        {--force : Restaura de verdad. Sin esta opción sólo se enumera}';

    protected $description = 'Restaura declaraciones juradas desde una copia de declarations:backup';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $relative = (string) $this->argument('archive');

        if (! $disk->exists($relative)) {
            $this->error('No existe la copia: '.$relative);

            return self::FAILURE;
        }

        $zip = new ZipArchive();

        if ($zip->open($disk->path($relative)) !== true) {
            throw new RuntimeException('No se pudo abrir la copia: '.$relative);
        }

        $manifest = json_decode((string) $zip->getFromName('declaraciones.json'), true, 512, JSON_THROW_ON_ERROR);

        $pedidos = array_map('intval', (array) $this->option('id'));
        $candidatas = [];

        foreach ($manifest['declaraciones'] ?? [] as $fila) {
            $id = (int) ($fila['id'] ?? 0);

            if ($id === 0 || ($pedidos !== [] && ! in_array($id, $pedidos, true))) {
                continue;
            }

            $candidatas[$id] = [
                'fila' => $fila,
                'existe' => Declaration::withTrashed()->whereKey($id)->exists(),
            ];
        }

        if ($candidatas === []) {
            $this->info('La copia no contiene ninguna declaración que coincida.');
            $zip->close();

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'remitente', 'creada', 'estado'],
            array_map(static fn (array $c): array => [
                $c['fila']['id'],
                (string) ($c['fila']['remitente_nombre'] ?? ''),
                (string) ($c['fila']['created_at'] ?? ''),
                $c['existe'] ? 'ya existe — se omite' : 'se restaurará',
            ], $candidatas)
        );

        $restaurables = array_filter($candidatas, static fn (array $c): bool => ! $c['existe']);

        if ($restaurables === []) {
            $this->info('Todas existen ya. No hay nada que restaurar.');
            $zip->close();

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Simulación: no se ha restaurado nada. Repite con --force.');
            $zip->close();

            return self::SUCCESS;
        }

        $restauradas = 0;

        foreach ($restaurables as $id => $candidata) {
            $fila = $candidata['fila'];
            $items = $fila['items'] ?? [];
            $archivos = $fila['_archivos'] ?? [];

            unset($fila['items'], $fila['_archivos']);

            DB::transaction(function () use ($fila, $items, $archivos, $zip, $disk): void {
                $declaration = new Declaration();
                $declaration->forceFill($fila);
                $declaration->save();

                foreach ($items as $item) {
                    unset($item['id']);
                    $item['declaration_id'] = $declaration->getKey();
                    $declaration->items()->create($item);
                }

                foreach ($archivos as $dentro) {
                    $contenido = $zip->getFromName((string) $dentro);

                    if ($contenido === false) {
                        continue;
                    }

                    $destino = str_contains((string) $dentro, '/foto-')
                        ? $declaration->foto_dni_path
                        : $declaration->pdf_path;

                    if (is_string($destino) && $destino !== '') {
                        $disk->put($destino, $contenido);
                    }
                }
            });

            $restauradas++;
        }

        $zip->close();

        $this->info(sprintf('%d declaración(es) restaurada(s).', $restauradas));

        return self::SUCCESS;
    }
}
