<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Models\Ubigeo;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class UbigeoSyncService
{
    public function __construct(
        private readonly AlanubeUbigeoDownloader $downloader,
        private readonly AlanubeUbigeoParser $parser,
    ) {}

    public function sync(bool $dryRun = false, bool $noDownload = false, bool $force = false): array
    {
        if (! config('ubigeos.enabled') && ! $force) {
            throw new RuntimeException('La sincronización de UBIGEO está deshabilitada.');
        }
        $rows = $noDownload ? $this->snapshotRows() : $this->parser->parse($this->downloader->download());
        $this->validate($rows);
        $existing = Ubigeo::query()->get()->keyBy('codigo');
        $inserted = $updated = $ignored = 0;
        foreach ($rows as $row) {
            $current = $existing->get($row['codigo']);
            if ($current === null) {
                $inserted++;
            } elseif ($current->only(['departamento', 'provincia', 'distrito', 'capital']) === array_diff_key($row, ['codigo' => true])) {
                $ignored++;
            } else {
                $updated++;
            }
        }
        if (! $dryRun) {
            $now = now();
            $sourceUrl = (string) config('ubigeos.sources.alanube.url');
            foreach (array_chunk($rows, 500) as $chunk) {
                $payload = array_map(fn (array $row): array => $row + [
                    'source' => 'alanube', 'source_url' => $sourceUrl,
                    'source_updated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ], $chunk);
                Ubigeo::query()->upsert($payload, ['codigo'], ['departamento', 'provincia', 'distrito', 'capital', 'source', 'source_url', 'source_updated_at', 'updated_at']);
            }
            if (! $noDownload) {
                $this->writeSnapshot($rows);
            }
        }

        return compact('inserted', 'updated', 'ignored') + ['total' => count($rows), 'dry_run' => $dryRun];
    }

    public function validateCurrent(): array
    {
        $rows = Ubigeo::query()->get(['codigo', 'departamento', 'provincia', 'distrito', 'capital'])->toArray();
        $this->validate($rows);

        return ['total' => count($rows)];
    }

    public function snapshotRows(): array
    {
        $path = (string) config('ubigeos.snapshot');
        if (! File::exists($path)) {
            throw new RuntimeException('No existe el snapshot local de UBIGEO.');
        }
        $rows = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return is_array($rows) ? $rows : [];
    }

    private function validate(array $rows): void
    {
        $minimum = (int) config('ubigeos.minimum_rows', 1800);
        if (count($rows) < $minimum) {
            throw new RuntimeException('El catálogo descargado parece incompleto: '.count($rows)." filas; mínimo {$minimum}.");
        }
        $codes = [];
        foreach ($rows as $index => $row) {
            $code = (string) ($row['codigo'] ?? '');
            if (! preg_match('/^\d{6}$/', $code) || empty($row['departamento']) || empty($row['provincia']) || empty($row['distrito'])) {
                throw new RuntimeException('Fila UBIGEO inválida en la posición '.($index + 1).'.');
            }
            if (isset($codes[$code])) {
                throw new RuntimeException("El catálogo contiene el código duplicado {$code}.");
            }
            $codes[$code] = $row;
        }
        foreach ([
            '010101' => ['AMAZONAS', 'CHACHAPOYAS', 'CHACHAPOYAS'],
            '150137' => ['LIMA', 'LIMA', 'SANTA ANITA'],
            '150140' => ['LIMA', 'LIMA', 'SANTIAGO DE SURCO'],
        ] as $code => $expected) {
            $row = $codes[$code] ?? null;
            if ($row === null || [$row['departamento'], $row['provincia'], $row['distrito']] !== $expected) {
                throw new RuntimeException("El código de control {$code} no coincide con el catálogo esperado.");
            }
        }
    }

    private function writeSnapshot(array $rows): void
    {
        $path = (string) config('ubigeos.snapshot');
        $temporary = $path.'.tmp';
        File::ensureDirectoryExists(dirname($path));
        File::put($temporary, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
        if (! File::move($temporary, $path)) {
            File::delete($temporary);
            throw new RuntimeException('No se pudo actualizar atómicamente el snapshot de UBIGEO.');
        }
    }
}
