<?php

namespace RucTool\Commands;

use RucTool\Database\Connection;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;
use RucTool\Services\BackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'backup', description: 'Backup ruc_records via pg_dump, split into fixed-size parts + manifest.json')]
class BackupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('part-size', null, InputOption::VALUE_REQUIRED, 'Tamaño de cada parte en MiB', '90')
            ->addOption('keep-full', null, InputOption::VALUE_NONE, 'Conservar también el .dump completo (por defecto se borra tras verificar el split)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $partSizeMib = (int) $input->getOption('part-size');
        if ($partSizeMib <= 0) {
            $io->error('--part-size debe ser un número entero de MiB mayor que 0.');

            return Command::FAILURE;
        }
        $partSizeBytes = $partSizeMib * 1024 * 1024;
        $keepFull = (bool) $input->getOption('keep-full');

        try {
            $configManager = new ConfigManager;
            $config = $configManager->all();

            $io->title('RUC Backup');

            $connection = new Connection($config['database']);
            $backupService = new BackupService($connection, $config['database'], $config['backup_directory']);

            $recordCount = $connection->count('ruc_records');
            $io->text('Registros:');
            $io->text('<info>'.number_format($recordCount).'</info>');
            $io->newLine();

            $io->text('Creando PostgreSQL dump...');

            $backup = $backupService->backup($partSizeBytes, $keepFull, function (string $stage, array $context) use ($io, $partSizeMib): void {
                switch ($stage) {
                    case 'dump_created':
                        $io->text('OK');
                        $io->newLine();
                        $io->text('Tamaño:');
                        $io->text('<info>'.$this->formatBytes($context['size_bytes']).'</info>');
                        $io->newLine();
                        $io->text('Validando dump...');
                        break;
                    case 'validated':
                        $io->text('OK');
                        break;
                    case 'checksummed':
                        $io->newLine();
                        $io->text('SHA-256:');
                        $io->text('<comment>'.$context['checksum'].'</comment>');
                        $io->newLine();
                        $io->text("Dividiendo en partes de {$partSizeMib} MiB...");
                        $io->newLine();
                        break;
                    case 'part_created':
                        $part = $context['part'];
                        $io->text(sprintf(
                            'Parte %d/%d  %s  OK',
                            $part['index'],
                            $context['total_parts'],
                            str_pad($this->formatBytes($part['size_bytes']), 10, ' ', STR_PAD_LEFT)
                        ));
                        break;
                    case 'verified':
                        $io->newLine();
                        $io->text('Verificando partes...');
                        $io->text('OK');
                        break;
                }
            });

            $io->newLine();
            $io->section('Manifest');
            $io->table(
                ['Propiedad', 'Valor'],
                [
                    ['Nombre', $backup['name']],
                    ['Directorio', $backup['directory']],
                    ['Manifest', basename($backup['manifest_path'])],
                    ['Partes', (string) count($backup['parts'])],
                    ['Tamaño de parte', $this->formatBytes($backup['part_size_bytes'])],
                    ['Registros', number_format($backup['records'])],
                    ['SHA-256', $backup['checksum']],
                    ['Dump completo conservado', $backup['kept_full'] ? 'sí (--keep-full)' : 'no (borrado tras verificar)'],
                ]
            );

            $io->success('Backup preparado correctamente.');
            $io->note('Verifica en destino con: ruc-tool backup:verify '.basename($backup['manifest_path']));
            Logger::info('Backup created: '.$backup['name'].' ('.count($backup['parts']).' parts)');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Backup failed: '.$e->getMessage());
            Logger::error('Backup failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 1).' '.$units[$pow];
    }
}
