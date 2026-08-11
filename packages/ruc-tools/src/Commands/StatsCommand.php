<?php

namespace RucTool\Commands;

use RucTool\Database\Connection;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'stats', description: 'Show database statistics')]
class StatsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $configManager = new ConfigManager;
            $config = $configManager->all();
            $connection = new Connection($config['database']);

            $io->title('RUC Database Statistics');

            $totalRecords = $connection->count('ruc_records');
            $activeRecords = $connection->query(
                "SELECT COUNT(*) as count FROM ruc_records WHERE estado ILIKE 'ACTIVO'"
            )->fetch()['count'] ?? 0;
            $withUbigeo = $connection->query(
                'SELECT COUNT(*) as count FROM ruc_records WHERE departamento IS NOT NULL'
            )->fetch()['count'] ?? 0;
            $ubigeoCount = $connection->count('ubigeos');
            $imports = $connection->count('ruc_tool_imports');
            $backups = $connection->count('ruc_tool_backups');

            $lastImport = $connection->query(
                'SELECT filename, completed_at, inserted_records, valid_lines FROM ruc_tool_imports ORDER BY id DESC LIMIT 1'
            )->fetch();

            $io->section('Database Summary');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Records', number_format($totalRecords)],
                    ['Active (ACTIVO)', number_format($activeRecords)],
                    ['With resolved ubigeo', number_format($withUbigeo)],
                    ['Ubigeo catalog size', number_format($ubigeoCount)],
                    ['Import runs', number_format($imports)],
                    ['Backups', number_format($backups)],
                ]
            );

            if ($lastImport) {
                $io->section('Last Import');
                $io->table(
                    ['Field', 'Value'],
                    [
                        ['Filename', $lastImport['filename']],
                        ['Valid Lines', number_format($lastImport['valid_lines'])],
                        ['Inserted/Updated', number_format($lastImport['inserted_records'])],
                        ['Completed At', $lastImport['completed_at']],
                    ]
                );
            }

            $stateStats = $connection->query(
                'SELECT estado, COUNT(*) as count FROM ruc_records GROUP BY estado ORDER BY count DESC LIMIT 10'
            )->fetchAll();

            if (! empty($stateStats)) {
                $io->section('Top Records by Estado');
                $rows = array_map(fn ($s) => [$s['estado'] ?? 'N/A', number_format($s['count'])], $stateStats);
                $io->table(['Estado', 'Count'], $rows);
            }

            $deptStats = $connection->query(
                'SELECT departamento, COUNT(*) as count FROM ruc_records WHERE departamento IS NOT NULL GROUP BY departamento ORDER BY count DESC LIMIT 10'
            )->fetchAll();

            if (! empty($deptStats)) {
                $io->section('Top Departamentos');
                $rows = array_map(fn ($s) => [$s['departamento'], number_format($s['count'])], $deptStats);
                $io->table(['Departamento', 'Count'], $rows);
            }

            Logger::info('Stats command executed');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to retrieve stats: '.$e->getMessage());
            Logger::error('Stats command failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
