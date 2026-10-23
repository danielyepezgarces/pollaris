<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Community;

use App\Service\CommunityNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:community:send-reminders',
    description: 'Send automated reminders for community polls expiring in the next 24 hours to members who have not voted yet',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private CommunityNotificationService $notificationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Checking for community polls due for reminder...');

        $remindedCount = $this->notificationService->sendDueReminders();

        $io->success(sprintf('Reminders processed for %d community poll(s).', $remindedCount));

        return Command::SUCCESS;
    }
}
