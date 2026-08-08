<?php

namespace RucTool\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use RucTool\Database\Connection;
use RucTool\Services\BackupService;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;

#[AsCommand(name: 'restore', description: 'Restore ruc_records from a local pg_dump backup (.dump, legacy .sql.gz, or *.manifest.json)')]
class RestoreCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('backup', InputArgument::REQUIRED, 'Backup filename, path, or *.manifest.json to restore (see: ruc-tool backup)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $backupFilename = $input->getArgument('backup');
        $isManifest = str_ends_with($backupFilename, '.manifest.json');

        try {
            $configManager = new ConfigManager();
            $config = $configManager->all();
            $connection = new Connection($config['database']);
            $backupService = new BackupService($connection, $config['database'], $config['backup_directory']);

            $io->title('RUC Database Restore (pg_restore)');
            $io->warning('⚠️  This will TRUNCATE ruc_records and restore from backup!');
            $io->newLine();

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Are you sure you want to continue? (yes/no): ', false);

            if (!$helper->ask($input, $output, $question)) {
                $io->warning('Restore cancelled');
                return Command::SUCCESS;
            }

            $io->info("Restoring from: $backupFilename");

            if ($isManifest) {
                $io->text('Detectado manifest.json: verificando partes y reconstruyendo el backup...');
                $result = $backupService->restoreFromManifest($backupFilename);
            } else {
                $result = $backupService->restore($backupFilename);
            }

            $io->newLine();
            $io->section('Restore Completed');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Backup File', $result['backup_file']],
                    ['Records Before', number_format($result['records_before'])],
                    ['Records After', number_format($result['records_after'])],
                    ['Status', 'Success'],
                ]
            );

            $io->success('Database restored successfully');
            Logger::info("Database restored from: $backupFilename");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Restore failed: ' . $e->getMessage());
            Logger::error('Restore failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
