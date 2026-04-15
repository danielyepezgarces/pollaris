<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Adrien Scholaert <adrien@framasoft.org>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Repository;
use App\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PollsCleaner
{
    public function __construct(
        private Repository\PollRepository $pollRepository,
        #[Autowire('%poll.expires.completed%')]
        private readonly string $completedDelay,
        #[Autowire('%poll.expires.incomplete%')]
        private readonly string $incompleteDelay,
    ) {
    }

    public function execute(): int
    {
        if (!$this->completedDelay || !$this->incompleteDelay) {
            throw new PollsCleanerError('POLL_EXPIRES_COMPLETED or POLL_EXPIRES_INCOMPLETED env variable is not set');
        }

        $completedExpirationDate = Utils\Time::relative('-' . $this->completedDelay);
        $incompleteExpirationDate = Utils\Time::relative('-' . $this->incompleteDelay);

        return $this->pollRepository->deleteExpiredPolls($completedExpirationDate, $incompleteExpirationDate);
    }

    public function count(): int
    {
        if (!$this->completedDelay || !$this->incompleteDelay) {
            throw new PollsCleanerError('POLL_EXPIRES_COMPLETED or POLL_EXPIRES_INCOMPLETED env variable is not set');
        }

        $completedExpirationDate = Utils\Time::relative('-' . $this->completedDelay);
        $incompleteExpirationDate = Utils\Time::relative('-' . $this->incompleteDelay);

        return $this->pollRepository->countExpiredPolls($completedExpirationDate, $incompleteExpirationDate);
    }
}
