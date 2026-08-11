<?php

namespace RucTool\Commands;

use RucTool\Database\Connection;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'export', description: 'Export ruc_records to CSV/JSON')]
class ExportCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Export format (csv, json)', 'csv')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path', 'ruc_export.csv')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of records', null)
            ->addOption('estado', null, InputOption::VALUE_OPTIONAL, 'Filter by estado')
            ->addOption('departamento', null, InputOption::VALUE_OPTIONAL, 'Filter by departamento');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $format = $input->getOption('format');
            $outputFile = $input->getOption('output');
            $limit = $input->getOption('limit');
            $estado = $input->getOption('estado');
            $departamento = $input->getOption('departamento');

            $configManager = new ConfigManager;
            $config = $configManager->all();
            $connection = new Connection($config['database']);

            $io->title('RUC Export');
            $io->info("Exporting to: $outputFile");

            $sql = 'SELECT * FROM ruc_records WHERE 1=1';
            $params = [];

            if ($estado) {
                $sql .= ' AND estado ILIKE ?';
                $params[] = $estado;
            }

            if ($departamento) {
                $sql .= ' AND departamento ILIKE ?';
                $params[] = $departamento;
            }

            $sql .= ' ORDER BY id';

            if ($limit) {
                $sql .= ' LIMIT '.(int) $limit;
            }

            $records = $connection->query($sql, $params)->fetchAll();
            $recordCount = count($records);

            match ($format) {
                'csv' => $this->exportCsv($outputFile, $records),
                'json' => $this->exportJson($outputFile, $records),
                default => throw new \Exception("Unsupported format: $format"),
            };

            $io->success('Export completed successfully');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Format', strtoupper($format)],
                    ['Output File', $outputFile],
                    ['Records Exported', number_format($recordCount)],
                    ['File Size', $this->formatBytes(filesize($outputFile))],
                ]
            );

            Logger::info("Exported $recordCount records to $outputFile");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Export failed: '.$e->getMessage());
            Logger::error('Export failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function exportCsv(string $filename, array $records): void
    {
        $fp = fopen($filename, 'w');

        if (! empty($records)) {
            fputcsv($fp, array_keys($records[0]));
            foreach ($records as $record) {
                fputcsv($fp, $record);
            }
        }

        fclose($fp);
    }

    private function exportJson(string $filename, array $records): void
    {
        file_put_contents($filename, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
