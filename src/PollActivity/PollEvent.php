<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\PollActivity;

use App\Entity;
use Symfony\Contracts\EventDispatcher\Event;

class PollEvent extends Event
{
    public const COMPLETED = 'poll.completed';

    public function __construct(
        private Entity\Poll $poll
    ) {
    }

    public function getPoll(): Entity\Poll
    {
        return $this->poll;
    }
}
