<?php

namespace RucTool\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use RucTool\Services\BackupPartitioner;
use RucTool\Services\ManifestService;
use RucTool\Helpers\Logger;

#[AsCommand(name: 'backup:verify', description: 'Verify a split backup: manifest, parts, checksums, and reconstructed SHA-256')]
class BackupVerifyCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('manifest', InputArgument::REQUIRED, 'Path to the *.manifest.json file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $manifestPath = $input->getArgument('manifest');

        $io->title('RUC Backup Verify');

        try {
            $manifestService = new ManifestService();
            $manifest = $manifestService->read($manifestPath);
            $errors = $manifestService->validate($manifest, dirname($manifestPath), new BackupPartitioner());

            if (empty($errors)) {
                $io->table(
                    ['Propiedad', 'Valor'],
                    [
                        ['Backup', $manifest['original_filename'] ?? '—'],
                        ['Registros', number_format($manifest['total_records'] ?? 0)],
                        ['Partes', (string) ($manifest['total_parts'] ?? 0)],
                        ['Tamaño total', number_format($manifest['total_size_bytes'] ?? 0) . ' bytes'],
                        ['SHA-256', $manifest['sha256'] ?? '—'],
                    ]
                );
                $io->success('Backup válido.');
                Logger::info('Backup verified OK: ' . $manifestPath);

                return Command::SUCCESS;
            }

            foreach ($errors as $error) {
                $io->error($error);
            }
            Logger::error('Backup verify failed: ' . $manifestPath . ' — ' . implode(' | ', $errors));

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Verify failed: ' . $e->getMessage());
            Logger::error('Backup verify failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
