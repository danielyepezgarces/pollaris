<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

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
