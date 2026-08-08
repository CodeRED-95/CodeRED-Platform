<?php

namespace RucTool\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use RucTool\Database\Connection;
use RucTool\Services\ImportService;
use RucTool\Services\PadronParser;
use RucTool\Services\UbigeoService;
use RucTool\Services\BackupService;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;

#[AsCommand(name: 'import', description: 'Import padrón reducido RUC (SUNAT .txt) into ruc_records')]
class ImportCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to padron_reducido_ruc.txt')
            ->addOption('encoding', null, InputOption::VALUE_OPTIONAL, 'File encoding', 'ISO-8859-1')
            ->addOption('delimiter', null, InputOption::VALUE_OPTIONAL, 'Field delimiter', '|')
            ->addOption(
                'strategy',
                's',
                InputOption::VALUE_OPTIONAL,
                'Merge strategy: insert (skip existing RUCs) or update (overwrite existing RUCs)',
                'insert'
            )
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Rows per COPY batch', '50000')
            ->addOption('skip-backup', null, InputOption::VALUE_NONE, 'Skip creating a backup before import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filepath = $input->getArgument('file');

        try {
            if (!file_exists($filepath)) {
                $io->error("File not found: $filepath");
                return Command::FAILURE;
            }

            $configManager = new ConfigManager();
            $config = $configManager->all();
            $connection = new Connection($config['database']);

            if (!$input->getOption('skip-backup') && $connection->count('ruc_records') > 0) {
                $io->info('Creating safety backup before import...');
                $backupService = new BackupService($connection, $config['database'], $config['backup_directory']);
                $backup = $backupService->backup();
                $io->success('Backup created: ' . $backup['filename']);
            }

            $parser = new PadronParser();
            $ubigeoService = new UbigeoService($connection);
            $importService = new ImportService($connection, $parser, $ubigeoService, $config);

            $io->title('RUC Import — Padrón Reducido SUNAT');
            $io->info("Importing from: $filepath");
            $io->info('Encoding: ' . $input->getOption('encoding') . ' | Delimiter: "' . $input->getOption('delimiter') . '" | Strategy: ' . $input->getOption('strategy'));

            $options = [
                'encoding' => $input->getOption('encoding'),
                'delimiter' => $input->getOption('delimiter'),
                'strategy' => $input->getOption('strategy'),
                'batch_size' => (int) $input->getOption('batch-size'),
            ];

            $stats = $importService->importFile($filepath, $options, function (array $progress) use ($io) {
                if (($progress['phase'] ?? null) === 'merge') {
                    $io->newLine();
                    $io->info('File fully read. Merging into ruc_records (deduplicating by RUC)...');
                    $io->note('This is a single large query — for 10M+ lines it can take several minutes with no visible progress. This is expected.');
                    return;
                }

                $pct = $progress['total'] > 0 ? round(($progress['processed'] / $progress['total']) * 100, 1) : 0;
                $io->writeln(sprintf(
                    "  %s%% | Processed: %s | Valid: %s | Errors: %s",
                    $pct,
                    number_format($progress['processed']),
                    number_format($progress['valid']),
                    number_format($progress['errors'])
                ));
            });

            $io->newLine();
            $io->section('Import Summary');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Lines', number_format($stats['total'])],
                    ['Valid Lines', number_format($stats['valid'])],
                    ['Errors', number_format($stats['errors'])],
                    ['Duplicates (in file)', number_format($stats['duplicates'])],
                    ['Inserted/Updated in ruc_records', number_format($stats['inserted'])],
                    ['Duration', $stats['duration_seconds'] . ' seconds'],
                    ['Speed', number_format($stats['lines_per_second']) . ' lines/second'],
                ]
            );

            $io->success('Import completed successfully');
            Logger::info("Import completed: {$stats['inserted']} records affected, {$stats['errors']} errors, {$stats['duplicates']} duplicates");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Import failed: ' . $e->getMessage());
            Logger::error('Import failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
