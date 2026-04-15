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
use App\Service;
use App\Utils;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Entity\Poll>
 */
final class PollFactory extends PersistentObjectFactory
{
    public function __construct(
        private Service\PollPassword $pollPassword,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'title' => self::faker()->words(3, true),
            'type' => 'classic',
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Entity\Poll $poll): void {
            if (!$poll->getPassword()) {
                return;
            }

            $hashedPassword = $this->pollPassword->hash($poll->getPassword());
            $poll->setPassword($hashedPassword);
        });
    }

    public static function class(): string
    {
        return Entity\Poll::class;
    }

    public function classic(): self
    {
        return $this->with(['type' => 'classic']);
    }

    public function date(): self
    {
        return $this->with(['type' => 'date']);
    }

    public function withProposal(): self
    {
        $proposal = ProposalFactory::new([
            'poll' => $this,
        ]);

        return $this
            ->classic()
            ->with([
                'proposals' => [$proposal],
            ]);
    }

    public function withDate(): self
    {
        $date = DateFactory::new([
            'poll' => $this,
        ]);

        return $this
            ->date()
            ->with([
                'dates' => [$date],
            ]);
    }

    public function withAuthor(): self
    {
        return $this->with([
            'authorName' => self::faker()->name(),
        ]);
    }

    public function completed(): self
    {
        return $this
            ->withProposal()
            ->withAuthor()
            ->with([
                'completedAt' => Utils\Time::now(),
            ]);
    }
}
