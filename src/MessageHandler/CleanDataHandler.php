<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Message;
use App\Service;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanDataHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private Service\PollsCleaner $pollsCleaner,
    ) {
    }

    public function __invoke(Message\CleanData $message): void
    {
        try {
            $countDeletedExpiredPolls = $this->pollsCleaner->execute();

            if ($countDeletedExpiredPolls > 0) {
                $this->logger->notice("[CleanData] {$countDeletedExpiredPolls} expired poll(s) deleted");
            }
        } catch (Service\PollsCleanerError $e) {
            // Do nothing on purpose as the admin didn't set expiration dates.
        }
    }
}
