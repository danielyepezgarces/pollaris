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

namespace App\Tests\Factory;

use App\Entity;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Entity\Answer>
 */
final class AnswerFactory extends PersistentObjectFactory
{
    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'vote' => VoteFactory::new(),
            'proposal' => ProposalFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Entity\Answer $answer, array $attributes): void {
            $vote = $answer->getVote();
            $proposal = $answer->getProposal();
            if ($vote && $proposal) {
                $vote->setPoll($proposal->getPoll());
            }
        });
    }

    public static function class(): string
    {
        return Entity\Answer::class;
    }
}
