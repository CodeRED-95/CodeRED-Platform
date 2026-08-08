<?php

namespace RucTool\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use RucTool\Services\PadronParser;
use RucTool\Helpers\Logger;

#[AsCommand(name: 'validate', description: 'Validate a padrón reducido RUC (.txt) file without importing it')]
class ValidateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to padron_reducido_ruc.txt')
            ->addOption('encoding', null, InputOption::VALUE_OPTIONAL, 'File encoding', 'ISO-8859-1')
            ->addOption('delimiter', null, InputOption::VALUE_OPTIONAL, 'Field delimiter', '|')
            ->addOption('save-report', 'r', InputOption::VALUE_OPTIONAL, 'Save error report to file', 'validation_report.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filepath = $input->getArgument('file');
        $encoding = $input->getOption('encoding');
        $delimiter = $input->getOption('delimiter');

        try {
            if (!file_exists($filepath)) {
                $io->error("File not found: $filepath");
                return Command::FAILURE;
            }

            $io->title('RUC Validation — Padrón Reducido SUNAT');
            $io->info("Validating: $filepath (encoding=$encoding, delimiter=\"$delimiter\")");

            $parser = new PadronParser();
            $handle = fopen($filepath, 'rb');

            $stats = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'headers_skipped' => 0, 'errors' => []];
            $lineNumber = 0;
            $seenRucs = [];
            $duplicates = 0;

            while (($rawLine = fgets($handle)) !== false) {
                $lineNumber++;
                $line = rtrim($rawLine, "\r\n");
                if ($line === '') {
                    continue;
                }

                $result = $parser->parse($line, $delimiter, $encoding);

                if (isset($result['header'])) {
                    $stats['headers_skipped']++;
                    continue;
                }

                $stats['total']++;

                if (isset($result['error'])) {
                    $stats['invalid']++;
                    if (count($stats['errors']) < 10000) {
                        $stats['errors'][] = ['line' => $lineNumber, 'error' => $result['error'], 'preview' => mb_substr($line, 0, 300)];
                    }
                    continue;
                }

                $ruc = $result['data']['ruc'];
                if (isset($seenRucs[$ruc])) {
                    $duplicates++;
                } else {
                    $seenRucs[$ruc] = true;
                }

                $stats['valid']++;

                if ($stats['total'] % 500000 === 0) {
                    $io->writeln('  Validated: ' . number_format($stats['total']) . ' lines');
                }
            }
            fclose($handle);

            $io->newLine();
            $io->section('Validation Results');
            $percentage = $stats['total'] > 0 ? round(($stats['valid'] / $stats['total']) * 100, 2) : 0;

            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Lines (excl. header)', number_format($stats['total'])],
                    ['Valid', number_format($stats['valid'])],
                    ['Invalid', number_format($stats['invalid'])],
                    ['Valid %', "$percentage%"],
                    ['Duplicate RUCs in file', number_format($duplicates)],
                    ['Header lines skipped', $stats['headers_skipped']],
                ]
            );

            if ($stats['invalid'] > 0) {
                $reportFile = $input->getOption('save-report');
                file_put_contents($reportFile, json_encode([
                    'timestamp' => date('Y-m-d H:i:s'),
                    'total_lines' => $stats['total'],
                    'valid_lines' => $stats['valid'],
                    'invalid_lines' => $stats['invalid'],
                    'duplicate_rucs' => $duplicates,
                    'errors' => $stats['errors'],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $io->warning("Found {$stats['invalid']} invalid lines");
                $io->note("Error report saved to: $reportFile" . (count($stats['errors']) >= 10000 ? ' (truncated to first 10,000 errors)' : ''));
            } else {
                $io->success('All lines are valid!');
            }

            Logger::info("Validation completed: {$stats['valid']} valid, {$stats['invalid']} invalid out of {$stats['total']}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Validation failed: ' . $e->getMessage());
            Logger::error('Validation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
