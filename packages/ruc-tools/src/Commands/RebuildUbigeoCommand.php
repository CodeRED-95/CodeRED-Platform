<?php

namespace RucTool\Commands;

use RucTool\Database\Connection;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;
use RucTool\Services\UbigeoService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ubigeo:rebuild', description: 'Re-resolve departamento/provincia/distrito on existing ruc_records from the ubigeos catalog (like ruc:rebuild-addresses)')]
class RebuildUbigeoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('only-missing', null, InputOption::VALUE_NONE, 'Only update records with a null departamento')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without writing changes')
            ->addOption('chunk-size', null, InputOption::VALUE_OPTIONAL, 'Rows per chunk', '5000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $configManager = new ConfigManager;
            $config = $configManager->all();
            $connection = new Connection($config['database']);
            $ubigeoService = new UbigeoService($connection);

            $onlyMissing = $input->getOption('only-missing');
            $dryRun = $input->getOption('dry-run');
            $chunkSize = (int) $input->getOption('chunk-size');

            $io->title('Rebuild Ubigeo — Resolve departamento/provincia/distrito');

            if ($ubigeoService->count() === 0) {
                $io->error('Ubigeo catalog is empty. Run `ruc-tool init` first (or re-run without --skip-ubigeo).');

                return Command::FAILURE;
            }

            $sql = 'SELECT id, ubigeo FROM ruc_records WHERE ubigeo IS NOT NULL';
            if ($onlyMissing) {
                $sql .= ' AND departamento IS NULL';
            }
            $sql .= ' ORDER BY id';

            $rows = $connection->query($sql)->fetchAll();
            $total = count($rows);

            if ($total === 0) {
                $io->success('Nothing to rebuild — no matching records.');

                return Command::SUCCESS;
            }

            $io->info('Records to process: '.number_format($total));

            $pdo = $connection->getPdo();
            $updateStmt = $pdo->prepare(
                'UPDATE ruc_records SET departamento = ?, provincia = ?, distrito = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
            );

            $updated = 0;
            $chunks = array_chunk($rows, $chunkSize);

            foreach ($chunks as $chunk) {
                if (! $dryRun) {
                    $pdo->beginTransaction();
                }

                foreach ($chunk as $row) {
                    $location = $ubigeoService->resolve($row['ubigeo']);
                    if ($location['departamento'] === null) {
                        continue;
                    }

                    if (! $dryRun) {
                        $updateStmt->execute([
                            $location['departamento'],
                            $location['provincia'],
                            $location['distrito'],
                            $row['id'],
                        ]);
                    }
                    $updated++;
                }

                if (! $dryRun) {
                    $pdo->commit();
                }

                $io->writeln('  Processed: '.number_format($updated).' / '.number_format($total));
            }

            $io->success(($dryRun ? 'Simulated' : 'Updated').': '.number_format($updated).' records');
            Logger::info("ubigeo:rebuild completed: $updated records ".($dryRun ? 'simulated' : 'updated'));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Rebuild failed: '.$e->getMessage());
            Logger::error('ubigeo:rebuild failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
