<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BumpVersionCommand extends Command
{
    protected $signature = 'app:bump-version {type : major|minor|patch} {--reason= : Motivo del cambio (ej: "RUC v3.0 release")}';
    protected $description = 'Incrementa la versión semántica y actualiza archivos de versión';

    public function handle(): int
    {
        $type = $this->argument('type');
        $reason = $this->option('reason') ?? 'Manual bump';

        if (!in_array($type, ['major', 'minor', 'patch'])) {
            $this->error("Tipo debe ser: major, minor o patch");
            return 1;
        }

        // Leer versión actual
        $current = $this->getCurrentVersion();
        $this->info("Versión actual: $current");

        // Calcular nueva versión
        $new = $this->bumpVersion($current, $type);
        $this->info("Nueva versión: $new");

        if (!$this->confirm("¿Confirmar bump a $new?")) {
            $this->line('Cancelado.');
            return 0;
        }

        // Actualizar archivos
        $this->updateComposerJson($new);
        $this->updateConfigFiles($new);
        $this->updateChangelogDate($new, $reason);

        $this->info("✅ Versión actualizada a: $new");
        $this->line("💡 Proximos pasos:");
        $this->line("  1. git add .");
        $this->line("  2. git commit -m \"chore: bump version to $new\"");
        $this->line("  3. git tag v$new");
        $this->line("  4. git push origin main --tags");

        return 0;
    }

    private function getCurrentVersion(): string
    {
        $composer = json_decode(File::get(base_path('composer.json')), true);
        return $composer['extra']['version'] ?? '0.0.0';
    }

    private function bumpVersion(string $current, string $type): string
    {
        [$major, $minor, $patch] = explode('.', $current);

        return match ($type) {
            'major' => ($major + 1) . '.0.0',
            'minor' => $major . '.' . ($minor + 1) . '.0',
            'patch' => $major . '.' . $minor . '.' . ($patch + 1),
        };
    }

    private function updateComposerJson(string $version): void
    {
        $path = base_path('composer.json');
        $composer = json_decode(File::get($path), true);
        $composer['extra']['version'] = $version;
        File::put($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $this->line("✓ composer.json actualizado");
    }

    private function updateConfigFiles(string $version): void
    {
        // config/version.php
        $versionConfig = base_path('config/version.php');
        if (File::exists($versionConfig)) {
            $content = File::get($versionConfig);
            $content = preg_replace(
                "/('current'\\s*=>\\s*env\\('APP_VERSION',\\s*')[^']+('\\))/",
                "$1$version$2",
                $content
            );
            File::put($versionConfig, $content);
            $this->line("✓ config/version.php actualizado");
        }

        // config/app.php
        $appConfig = base_path('config/app.php');
        if (File::exists($appConfig)) {
            $content = File::get($appConfig);
            $content = preg_replace(
                "/('version'\\s*=>\\s*env\\('APP_VERSION',\\s*env\\('APP_VERSION_FALLBACK',\\s*')[^']+('\\)\\))/",
                "$1$version$2",
                $content
            );
            File::put($appConfig, $content);
            $this->line("✓ config/app.php actualizado");
        }
    }

    private function updateChangelogDate(string $version, string $reason): void
    {
        $changelogPath = base_path('CHANGELOG.md');

        if (!File::exists($changelogPath)) {
            $this->warn("CHANGELOG.md no encontrado");
            return;
        }

        $content = File::get($changelogPath);
        $today = now()->format('Y-m-d');
        $placeholder = "## [UNRELEASED]";
        $newEntry = "## [$version] - $today\n\n### ℹ️ Nota\n- $reason\n\n";

        if (str_contains($content, $placeholder)) {
            $content = str_replace($placeholder, $newEntry . $placeholder, $content);
        } else {
            // Si no hay UNRELEASED, agregar después del header inicial
            $lines = explode("\n", $content);
            array_splice($lines, 2, 0, ["", $newEntry]);
            $content = implode("\n", $lines);
        }

        File::put($changelogPath, $content);
        $this->line("✓ CHANGELOG.md actualizado");
    }
}
