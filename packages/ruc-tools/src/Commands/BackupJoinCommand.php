<?php

namespace RucTool\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use RucTool\Services\BackupPartitioner;
use RucTool\Services\DumpValidator;
use RucTool\Services\ManifestService;
use RucTool\Helpers\Logger;

#[AsCommand(name: 'backup:join', description: 'Reconstruct the full .dump file from a manifest (streaming, byte-identical to the original)')]
class BackupJoinCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('manifest', InputArgument::REQUIRED, 'Path to the *.manifest.json file')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output path (default: alongside the manifest, using original_filename)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $manifestPath = $input->getArgument('manifest');
        $manifestDir = dirname($manifestPath);

        $io->title('RUC Backup Join');

        try {
            $manifestService = new ManifestService();
            $partitioner = new BackupPartitioner();

            $manifest = $manifestService->read($manifestPath);

            $io->text('Verificando manifest y partes...');
            $errors = $manifestService->validate($manifest, $manifestDir, $partitioner);
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $io->error($error);
                }

                return Command::FAILURE;
            }
            $io->text('OK');
            $io->newLine();

            $outputPath = $input->getOption('output') ?? ($manifestDir . '/' . $manifest['original_filename']);

            $partPaths = array_map(
                static fn (array $p): string => $manifestDir . '/' . $p['filename'],
                $manifest['parts']
            );

            $io->text('Reconstruyendo ' . basename($outputPath) . '...');
            $partitioner->join($partPaths, $outputPath);
            $io->text('OK');

            $actualSha = hash_file('sha256', $outputPath);
            if ($actualSha !== $manifest['sha256']) {
                $io->error('El archivo reconstruido NO coincide con el SHA-256 del manifest.');

                return Command::FAILURE;
            }
            $io->text('SHA-256 reconstruido coincide con el manifest: <info>OK</info>');

            $io->text('Verificando con pg_restore --list...');
            (new DumpValidator())->assertBelongsToRucRecords($outputPath);
            $io->text('OK');

            $io->success('Archivo reconstruido correctamente: ' . $outputPath);
            Logger::info('Backup joined: ' . $outputPath);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Join failed: ' . $e->getMessage());
            Logger::error('Backup join failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
