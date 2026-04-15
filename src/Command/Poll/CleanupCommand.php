<?php

// This file is part of Pollaris.
// Copyright 2026 Adrien Scholaert <adrien@framasoft.org>
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Poll;

use App\Service;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:poll:cleanup',
    description: 'Delete expired polls',
)]
final class CleanupCommand extends Command
{
    public function __construct(
        private Service\PollsCleaner $pollsCleaner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Do not delete anything, only show what would be deleted'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Expired polls cleanup');

        if ($dryRun) {
            $io->warning('Dry-run mode enabled: no data will be deleted.');

            try {
                $count = $this->pollsCleaner->count();
            } catch (Service\PollsCleanerError $e) {
                $io->error($e->getMessage());
                return Command::FAILURE;
            }

            if ($count === 0) {
                $io->success('No expired polls found.');
            } else {
                $io->success("{$count} poll(s) would be deleted.");
            }
        } else {
            try {
                $totalDeleted = $this->pollsCleaner->execute();
            } catch (Service\PollsCleanerError $e) {
                $io->error($e->getMessage());
                return Command::FAILURE;
            }

            if ($totalDeleted === 0) {
                $io->success('No expired polls to delete.');
            } else {
                $io->success("{$totalDeleted} expired poll(s) successfully deleted.");
            }
        }

        return Command::SUCCESS;
    }
}
