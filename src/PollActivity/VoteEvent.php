<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\PollActivity;

use App\Entity;
use Symfony\Contracts\EventDispatcher\Event;

class VoteEvent extends Event
{
    public const NEW = 'vote.new';

    public function __construct(
        private Entity\Vote $vote
    ) {
    }

    public function getVote(): Entity\Vote
    {
        return $this->vote;
    }
}
