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

namespace App\Validator;

use App\Service;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class PollPasswordValidator extends ConstraintValidator
{
    public function __construct(
        private Service\PollPassword $pollPassword,
    ) {
    }

    public function validate(mixed $password, Constraint $constraint): void
    {
        if (!$constraint instanceof PollPassword) {
            throw new UnexpectedValueException($constraint, PollPassword::class);
        }

        if ($password === null || $password === '') {
            return;
        }

        if (!is_string($password)) {
            throw new UnexpectedValueException($password, 'string');
        }

        $poll = $constraint->poll;

        if (!$poll->isPasswordProtected()) {
            throw new LogicException('Poll is not protected by password');
        }

        /** @var string */
        $pollPassword = $poll->getPassword();

        $passwordIsValid = $this->pollPassword->verify($pollPassword, $password);

        if (!$passwordIsValid) {
            $this
                ->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
