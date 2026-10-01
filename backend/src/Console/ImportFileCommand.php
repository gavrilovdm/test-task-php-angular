<?php

declare(strict_types=1);

namespace App\Console;

use App\Entity\ImportJob;
use App\Repository\ImportJobRepository;
use App\Service\Import\ProductImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Synchronous import from CLI (handy for local checks; the API uses the queue). */
#[AsCommand(name: 'import:file', description: 'Import products from an xlsx file synchronously')]
final class ImportFileCommand extends Command
{
    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly ProductImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to the .xlsx file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        if (!is_file($path)) {
            $output->writeln(\sprintf('<error>File "%s" not found</error>', $path));

            return Command::FAILURE;
        }

        $job = new ImportJob(basename($path), $path);
        $this->jobs->save($job);
        $this->importer->run($job);

        $output->writeln(\sprintf(
            'Status: %s, rows: %d, created: %d, updated: %d, failed: %d',
            $job->getStatus(),
            $job->getTotalRows(),
            $job->getCreatedCount(),
            $job->getUpdatedCount(),
            $job->getFailedCount(),
        ));
        foreach ($job->getErrors() as $error) {
            $output->writeln(\sprintf('  [%s] row %s: %s', $error['level'], $error['row'] ?? '-', $error['message']));
        }

        return ImportJob::STATUS_COMPLETED === $job->getStatus() ? Command::SUCCESS : Command::FAILURE;
    }
}
