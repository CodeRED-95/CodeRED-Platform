<?php

namespace RucTool\Helpers;

class ConfigManager
{
    private string $configPath;
    private array $config = [];

    public function __construct(string $configPath = null)
    {
        if ($configPath === null) {
            $configPath = getenv('HOME') . '/.ruc-tool/ruc-tool.json';
        }

        $this->configPath = $configPath;
        $this->load();
    }

    private function load(): void
    {
        if (file_exists($this->configPath)) {
            $contents = file_get_contents($this->configPath);
            $this->config = json_decode($contents, true) ?? [];
        } else {
            $this->config = $this->getDefaults();
        }
    }

    private function getDefaults(): array
    {
        return [
            'database' => [
                'driver' => 'pgsql',
                'host' => 'localhost',
                'port' => 5432,
                'database' => 'ruc_db',
                'username' => 'ruc_user',
                'password' => null,
            ],
            'backup_directory' => getenv('HOME') . '/.ruc-tool/backups',
            'logs_directory' => getenv('HOME') . '/.ruc-tool/logs',
            'copy_batch_size' => 50000,
            'timeout' => 3600,
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedKey($this->config, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->setNestedKey($this->config, $key, $value);
    }

    public function all(): array
    {
        return $this->config;
    }

    public function save(): void
    {
        $dir = dirname($this->configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->configPath,
            json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function getNestedKey(array $array, string $key, mixed $default): mixed
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    private function setNestedKey(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    public function isDatabaseConfigured(): bool
    {
        return isset($this->config['database']) && !empty($this->config['database']);
    }
}
