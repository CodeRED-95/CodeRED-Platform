<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class BumpVersionCommand extends Command
{
    protected $signature = 'app:bump-version
        {type : major|minor|patch}
        {--reason= : Motivo del cambio (se añade al CHANGELOG)}
        {--dry-run : Muestra la versión resultante sin escribir nada}';

    protected $description = 'Incrementa la versión semántica en la fuente única de verdad (composer.json > extra.version).';

    public function handle(): int
    {
        $type = (string) $this->argument('type');

        if (! in_array($type, ['major', 'minor', 'patch'], true)) {
            $this->error('Tipo debe ser: major, minor o patch.');

            return self::FAILURE;
        }

        $current = Version::current();

        try {
            $new = Version::bump($current, $type);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Fuente de verdad: <comment>'.Version::sourcePath().'</comment>');
        $this->line("Versión actual:   <comment>{$current}</comment>");
        $this->line("Nueva versión:    <info>{$new}</info>");

        if ($this->option('dry-run')) {
            $this->comment('--dry-run: no se ha modificado ningún archivo.');

            return self::SUCCESS;
        }

        if (! $this->confirm("¿Confirmar bump a {$new}?", ! $this->input->isInteractive())) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        try {
            // Un único archivo cambia. La configuración de Laravel, la UI, la
            // API y los scripts derivan de aquí, así que no hay nada más que
            // mantener sincronizado.
            Version::write($new);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('✓ composer.json actualizado (extra.version)');

        $this->updateChangelog($new, (string) ($this->option('reason') ?: 'Bump manual'));

        $this->newLine();
        $this->info("✅ Versión actualizada a: {$new}");
        $this->line('💡 Próximos pasos:');
        $this->line('  1. Revise el bloque nuevo de CHANGELOG.md');
        $this->line("  2. git commit -am \"chore: bump version to {$new}\"");
        $this->line("  3. git tag v{$new} && git push origin main --tags");
        $this->line('  4. En el servidor: ./update.sh');

        return self::SUCCESS;
    }

    /**
     * Inserta la entrada nueva justo antes de la primera versión listada, de
     * modo que la cabecera introductoria del CHANGELOG se mantiene arriba.
     */
    private function updateChangelog(string $version, string $reason): void
    {
        $path = base_path('CHANGELOG.md');

        if (! File::exists($path)) {
            $this->warn('CHANGELOG.md no encontrado; se omite.');

            return;
        }

        $content = File::get($path);
        $entry = sprintf("## [%s] - %s\n\n### ℹ️ Nota\n\n- %s\n\n---\n\n", $version, now()->format('Y-m-d'), $reason);

        if (str_contains($content, '## [UNRELEASED]')) {
            $content = str_replace('## [UNRELEASED]', rtrim($entry)."\n\n## [UNRELEASED]", $content);
        } elseif (preg_match('/^## \[\d+\.\d+\.\d+\]/m', $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $offset = (int) $match[0][1];
            $content = substr($content, 0, $offset).$entry.substr($content, $offset);
        } else {
            $content = rtrim($content)."\n\n".$entry;
        }

        File::put($path, $content);
        $this->line('✓ CHANGELOG.md actualizado');
    }
}
