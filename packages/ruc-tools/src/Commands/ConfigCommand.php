<?php

namespace RucTool\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;

#[AsCommand(name: 'config', description: 'Manage RUC tool configuration')]
class ConfigCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addArgument(
                'action',
                InputArgument::OPTIONAL,
                'Action: show, set, or reset',
                'show'
            )
            ->addArgument(
                'key',
                InputArgument::OPTIONAL,
                'Configuration key (for set action)'
            )
            ->addArgument(
                'value',
                InputArgument::OPTIONAL,
                'Configuration value (for set action)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'show';

        try {
            $configManager = new ConfigManager();

            return match ($action) {
                'show' => $this->showConfig($io, $configManager),
                'set' => $this->setConfig($io, $configManager, $input),
                'reset' => $this->resetConfig($io, $configManager, $input, $output),
                default => $this->showHelp($io),
            };
        } catch (\Exception $e) {
            $io->error('Config operation failed: ' . $e->getMessage());
            Logger::error('Config command failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showConfig(SymfonyStyle $io, ConfigManager $configManager): int
    {
        $io->title('RUC Tool Configuration');

        $config = $configManager->all();

        $io->section('Database Configuration');
        $database = $config['database'] ?? [];
        $io->table(
            ['Key', 'Value'],
            [
                ['Driver', $database['driver'] ?? 'N/A'],
                ['Host', $database['host'] ?? 'N/A'],
                ['Port', $database['port'] ?? 'N/A'],
                ['Database', $database['database'] ?? 'N/A'],
            ]
        );

        $io->section('Application Settings');
        $io->table(
            ['Key', 'Value'],
            [
                ['Backup Directory', $config['backup_directory'] ?? 'N/A'],
                ['Logs Directory', $config['logs_directory'] ?? 'N/A'],
                ['Workers', $config['workers'] ?? 'N/A'],
                ['Batch Size', $config['batch_size'] ?? 'N/A'],
                ['Timeout (s)', $config['timeout'] ?? 'N/A'],
            ]
        );

        $configPath = getenv('HOME') . '/.ruc-tool/ruc-tool.json';
        $io->note("Configuration file: $configPath");

        return Command::SUCCESS;
    }

    private function setConfig(SymfonyStyle $io, ConfigManager $configManager, InputInterface $input): int
    {
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');

        if (!$key || !$value) {
            $io->error('Usage: ruc-tool config set <key> <value>');
            $io->writeln('Example: ruc-tool config set workers 8');
            return Command::FAILURE;
        }

        // Parse value
        $parsedValue = $this->parseValue($value);

        $configManager->set($key, $parsedValue);
        $configManager->save();

        $io->success("Configuration updated: $key = $value");
        Logger::info("Configuration updated: $key = $value");

        return Command::SUCCESS;
    }

    private function resetConfig(SymfonyStyle $io, ConfigManager $configManager, InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $question = new \Symfony\Component\Console\Question\ConfirmationQuestion(
            'Reset configuration to defaults? (yes/no): ',
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $io->warning('Operation cancelled');
            return Command::SUCCESS;
        }

        // Remove config file
        $configPath = getenv('HOME') . '/.ruc-tool/ruc-tool.json';
        if (file_exists($configPath)) {
            unlink($configPath);
        }

        $io->success('Configuration reset to defaults');
        Logger::info('Configuration reset to defaults');

        return Command::SUCCESS;
    }

    private function showHelp(SymfonyStyle $io): int
    {
        $io->title('Configuration Commands');
        $io->writeln('
  <comment>show</comment>      Display current configuration
  <comment>set</comment>       Set a configuration value
  <comment>reset</comment>     Reset to default configuration

Examples:
  ruc-tool config show
  ruc-tool config set workers 8
  ruc-tool config set database.host localhost
  ruc-tool config reset
        ');

        return Command::SUCCESS;
    }

    private function parseValue(string $value): mixed
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => is_numeric($value) ? (int)$value : $value,
        };
    }
}
