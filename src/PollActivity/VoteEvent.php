<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Modified by Daniel Yepez Garces on 2026-04-15:
// - Migrated database backend from PostgreSQL to MariaDB for Toolforge deployment
// - Added Wikimedia login support
// - Removed local username/password authentication
// - Added multilingual survey support
// - Added user timezone display for survey times when different from server UTC

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
