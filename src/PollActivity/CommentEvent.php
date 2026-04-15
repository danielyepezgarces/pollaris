<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\PollActivity;

use App\Entity;
use Symfony\Contracts\EventDispatcher\Event;

class CommentEvent extends Event
{
    public const NEW = 'comment.new';

    public function __construct(
        private Entity\Comment $comment
    ) {
    }

    public function getComment(): Entity\Comment
    {
        return $this->comment;
    }
}
