<?php

namespace RucTool\Commands;

use RucTool\Database\Connection;
use RucTool\Database\Schema;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;
use RucTool\Services\UbigeoService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'init', description: 'Initialize RUC database (PostgreSQL, schema compatible with CodeRED-Platform)')]
class InitCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Database host', 'localhost')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Database port', '5432')
            ->addOption('database', null, InputOption::VALUE_OPTIONAL, 'Database name', 'ruc_db')
            ->addOption('username', 'u', InputOption::VALUE_OPTIONAL, 'Database username', 'ruc_user')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Database password')
            ->addOption('skip-ubigeo', null, InputOption::VALUE_NONE, 'Skip seeding the ubigeos catalog');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $configManager = new ConfigManager;

            $config = [
                'driver' => 'pgsql',
                'host' => $input->getOption('host'),
                'port' => (int) $input->getOption('port'),
                'database' => $input->getOption('database'),
                'username' => $input->getOption('username'),
                'password' => $input->getOption('password'),
            ];

            $io->info('Initializing RUC database...');

            $configManager->set('database', $config);
            $configManager->save();

            $io->success('Configuration saved to: '.getenv('HOME').'/.ruc-tool/ruc-tool.json');

            $connection = new Connection($config);
            $schema = new Schema($connection);
            $schema->create();

            $io->success('Database schema initialized (ruc_records, ubigeos, ruc_staging)');
            $io->note("Database: {$config['database']} at {$config['host']}:{$config['port']}");

            if (! $input->getOption('skip-ubigeo')) {
                $ubigeoPath = dirname(__DIR__, 2).'/resources/data/ubigeos_alanube.json';
                if (file_exists($ubigeoPath)) {
                    $ubigeoService = new UbigeoService($connection);
                    $count = $ubigeoService->seed($ubigeoPath);
                    $io->success("Ubigeo catalog seeded: $count records");
                } else {
                    $io->warning("Ubigeo seed file not found: $ubigeoPath (skipped)");
                }
            }

            Logger::info('Database initialized (pgsql)');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Initialization failed: '.$e->getMessage());
            Logger::error('Initialization failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
