<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Validator;

use App\Entity;
use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PollPassword extends Constraint
{
    public Entity\Poll $poll;

    public string $message = 'The password is incorrect.';

    public function __construct(
        Entity\Poll $poll,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->poll = $poll;
    }
}
