<?php

namespace RucTool\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use RucTool\Database\Connection;
use RucTool\Services\BackupService;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;

#[AsCommand(name: 'backup', description: 'Backup ruc_records via pg_dump --format=custom (restorable in production with php artisan ruc:restore)')]
class BackupCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $configManager = new ConfigManager();
            $config = $configManager->all();

            $io->title('RUC Database Backup (pg_dump --format=custom)');
            $io->info('Creating backup...');

            $connection = new Connection($config['database']);
            $backupService = new BackupService($connection, $config['database'], $config['backup_directory']);

            $backup = $backupService->backup();

            $io->newLine();
            $io->section('Backup Created');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Filename', $backup['filename']],
                    ['Location', $backup['path']],
                    ['Size', $this->formatBytes($backup['size'])],
                    ['Records', number_format($backup['records'])],
                    ['SHA-256', substr($backup['checksum'], 0, 16) . '...'],
                    ['Created At', $backup['timestamp']],
                ]
            );

            $backups = $backupService->listBackups();
            if (count($backups) > 1) {
                $io->section('Recent Backups');
                $rows = array_slice($backups, 0, 5);
                $tableRows = array_map(fn($b) => [$b['filename'], $b['size'], number_format($b['records']), $b['created']], $rows);
                $io->table(['Filename', 'Size', 'Records', 'Created'], $tableRows);
            }

            $io->success('Backup created successfully');
            $io->note('This file is compatible with `php artisan ruc:restore` on the production server.');
            Logger::info('Backup created: ' . $backup['filename']);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Backup failed: ' . $e->getMessage());
            Logger::error('Backup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
