<?php

namespace RucTool\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use RucTool\Database\Connection;
use RucTool\Helpers\ConfigManager;
use RucTool\Helpers\Logger;

#[AsCommand(name: 'search', description: 'Search for RUC records')]
class SearchCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('ruc', 'r', InputOption::VALUE_REQUIRED, 'Search by exact RUC')
            ->addOption('razon', null, InputOption::VALUE_REQUIRED, 'Search by razón social (trigram)')
            ->addOption('ubigeo', 'u', InputOption::VALUE_REQUIRED, 'Search by UBIGEO code')
            ->addOption('departamento', null, InputOption::VALUE_REQUIRED, 'Filter by departamento')
            ->addOption('estado', 's', InputOption::VALUE_REQUIRED, 'Filter by estado')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Max results', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $configManager = new ConfigManager();
            $config = $configManager->all();
            $connection = new Connection($config['database']);

            $ruc = $input->getOption('ruc');
            $razon = $input->getOption('razon');
            $ubigeo = $input->getOption('ubigeo');
            $departamento = $input->getOption('departamento');
            $estado = $input->getOption('estado');
            $limit = (int) $input->getOption('limit');

            if (!$ruc && !$razon && !$ubigeo && !$departamento && !$estado) {
                $io->error('Provide at least one search criterion (--ruc, --razon, --ubigeo, --departamento or --estado)');
                return Command::FAILURE;
            }

            $sql = 'SELECT * FROM ruc_records WHERE 1=1';
            $params = [];

            if ($ruc) {
                $sql .= ' AND ruc = ?';
                $params[] = $ruc;
            }

            if ($razon) {
                $sql .= ' AND razon_social ILIKE ?';
                $params[] = "%$razon%";
            }

            if ($ubigeo) {
                $sql .= ' AND ubigeo = ?';
                $params[] = $ubigeo;
            }

            if ($departamento) {
                $sql .= ' AND departamento ILIKE ?';
                $params[] = $departamento;
            }

            if ($estado) {
                $sql .= ' AND estado ILIKE ?';
                $params[] = $estado;
            }

            $sql .= ' LIMIT ' . $limit;

            $results = $connection->query($sql, $params)->fetchAll();

            if (empty($results)) {
                $io->info('No records found');
                return Command::SUCCESS;
            }

            $io->title('Search Results');
            $io->text('Found ' . count($results) . ' record(s)');
            $io->newLine();

            foreach ($results as $record) {
                $this->displayRecord($io, $record);
                $io->newLine();
            }

            Logger::info('Search executed: found ' . count($results) . ' records');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Search failed: ' . $e->getMessage());
            Logger::error('Search failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayRecord(SymfonyStyle $io, array $record): void
    {
        $io->section('RUC: ' . $record['ruc']);
        $io->definitionList(
            ['Razón Social' => $record['razon_social'] ?? 'N/A'],
            ['Estado' => $record['estado'] ?? 'N/A'],
            ['Condición' => $record['condicion'] ?? 'N/A'],
            ['UBIGEO' => $record['ubigeo'] ?? 'N/A'],
            ['Departamento' => $record['departamento'] ?? 'N/A'],
            ['Provincia' => $record['provincia'] ?? 'N/A'],
            ['Distrito' => $record['distrito'] ?? 'N/A'],
            ['Dirección' => $record['direccion'] ?? 'N/A'],
            ['Actualizado' => $record['updated_at'] ?? 'N/A'],
        );
    }
}
