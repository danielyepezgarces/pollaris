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

use App\Entity;
use Doctrine\Common\Collections;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class MaxVotesValidator extends ConstraintValidator
{
    public function validate(mixed $vote, Constraint $constraint): void
    {
        if (!$vote instanceof Entity\Vote) {
            throw new UnexpectedValueException($vote, Entity\Vote::class);
        }

        if (!$constraint instanceof MaxVotes) {
            throw new UnexpectedValueException($constraint, MaxVotes::class);
        }

        $poll = $vote->getPoll();

        // Count the actual "yes" answers per proposal, excluding the answers
        // of the actual vote.
        $countAnswersPerProposals = [];
        foreach ($poll->getProposals() as $proposal) {
            $excludeVote = $vote->getId() !== null ? $vote : null;
            $countYes = $proposal->countAnswers('yes', excludeVote: $excludeVote);
            $countAnswersPerProposals[$proposal->getId()] = $countYes;
        }

        // Check for each answer of the current vote if it is allowed by the
        // proposal's effective maxVotes (individual limit or poll's global limit).
        foreach ($vote->getAnswers() as $index => $answer) {
            $value = $answer->getValue();

            if ($value !== 'yes') {
                continue;
            }

            $proposal = $answer->getProposal();
            $proposalId = $proposal?->getId();

            if ($proposalId === null) {
                continue;
            }

            $maxVotes = $proposal->getEffectiveMaxVotes();
            if ($maxVotes === null || $maxVotes <= 0) {
                continue;
            }

            $actualCount = $countAnswersPerProposals[$proposalId] ?? 0;

            if ($actualCount + 1 <= $maxVotes) {
                continue;
            }

            $this
                ->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ max }}', (string) $maxVotes)
                ->atPath("answers[{$index}].value")
                ->addViolation();
        }
    }
}
