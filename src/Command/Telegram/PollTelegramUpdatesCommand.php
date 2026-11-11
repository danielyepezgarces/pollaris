<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Telegram;

use App\Service\TelegramService;
use App\Service\TelegramUpdateHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:telegram:poll',
    description: 'Continuously poll and process Telegram bot updates via long polling',
)]
class PollTelegramUpdatesCommand extends Command
{
    public function __construct(
        private TelegramService $telegramService,
        private TelegramUpdateHandler $updateHandler,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'time-limit',
            't',
            InputOption::VALUE_OPTIONAL,
            'Maximum execution time in seconds before exiting gracefully (useful for job runners like Toolforge continuous jobs)',
            3600
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->telegramService->isConfigured()) {
            $io->error('TELEGRAM_BOT_TOKEN is not configured in the environment.');
            return Command::FAILURE;
        }

        // Delete any existing webhook to ensure getUpdates receives all messages
        $this->telegramService->deleteWebhook();
        $io->success('Telegram long-polling worker started.');

        $timeLimit = (int) $input->getOption('time-limit');
        $startTime = time();
        $offset = 0;

        while (true) {
            // Check time limit
            if ($timeLimit > 0 && (time() - $startTime) >= $timeLimit) {
                $io->info('Time limit reached. Exiting gracefully for worker restart.');
                break;
            }

            try {
                $updates = $this->telegramService->getUpdates($offset, 50, 25);

                foreach ($updates as $update) {
                    $updateId = $update['update_id'] ?? null;
                    if ($updateId !== null) {
                        $offset = $updateId + 1;
                    }

                    try {
                        $this->updateHandler->handleUpdate($update);
                    } catch (\Throwable $e) {
                        $this->logger->error('Error processing Telegram update: ' . $e->getMessage(), ['exception' => $e]);
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->error('Error during Telegram long-polling: ' . $e->getMessage(), ['exception' => $e]);
                sleep(2);
            }
        }

        return Command::SUCCESS;
    }
}
