<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Command;

use MauticPlugin\MauticAspectFileBundle\Model\AspectFileModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to process AspectFile queues and generate/upload files
 */
class ProcessAspectFilesCommand extends Command
{
    private AspectFileModel $aspectFileModel;

    public function __construct(AspectFileModel $aspectFileModel)
    {
        $this->aspectFileModel = $aspectFileModel;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('mautic:aspectfile:process')
            ->setDescription('Process pending AspectFile batches and upload to MinIO')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of batches to process',
                10
            )
            ->setHelp(
                <<<'EOT'
The <info>%command.name%</info> command processes pending AspectFile batches.

<info>php %command.full_name%</info>

Process up to 50 batches:
<info>php %command.full_name% --limit=50</info>

This command should be run as a cron job every few minutes:
<info>*/5 * * * * php /path/to/mautic/bin/console mautic:aspectfile:process</info>
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        $io->title('AspectFile Batch Processor');

        // Check pending batches count
        $pendingCount = $this->aspectFileModel->getPendingBatchesCount();

        if (0 === $pendingCount) {
            $io->info('No pending batches to process');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf('Found <info>%d</info> pending batch(es)', $pendingCount));
        $io->newLine();

        // Process batches
        $io->section('Processing Batches');

        $stats = $this->aspectFileModel->processPendingBatches($limit);

        $io->table(
            ['Metric', 'Count'],
            [
                ['Batches Processed', $stats['processed']],
                ['Succeeded', $stats['succeeded']],
                ['Failed', $stats['failed']],
            ]
        );

        if ($stats['failed'] > 0) {
            $io->warning(sprintf('%d batch(es) failed. Check logs for details.', $stats['failed']));

            return Command::FAILURE;
        }

        $io->success(sprintf('Successfully processed %d batch(es)', $stats['succeeded']));

        return Command::SUCCESS;
    }
}
