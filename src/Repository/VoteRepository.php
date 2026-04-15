<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Entity\Vote>
 */
class VoteRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity\Vote::class);
    }

    /**
     * Find votes by poll and author name.
     *
     * This method is used to check uniqueness of author names, see Entity\Vote.
     *
     * @param array<string, mixed> $criteria
     * @return Entity\Vote[]
     */
    public function findByPollAndAuthorName(array $criteria): array
    {
        if (!isset($criteria['poll']) || !$criteria['poll'] instanceof Entity\Poll) {
            throw new \LogicException('Criteria must contain poll');
        }

        if (!isset($criteria['authorName']) || !is_string($criteria['authorName'])) {
            throw new \LogicException('Criteria must contain authorName');
        }

        $poll = $criteria['poll'];
        $authorName = $criteria['authorName'];
        $authorName = trim($authorName);
        $authorName = mb_strtolower($authorName);

        $votes = $poll->getVotes()->toArray();

        $votes = array_filter($votes, function (Entity\Vote $vote) use ($authorName): bool {
            $voteAuthorName = $vote->getAuthorName() ?? '';
            $voteAuthorName = trim($voteAuthorName);
            $voteAuthorName = mb_strtolower($voteAuthorName);

            return $authorName === $voteAuthorName;
        });

        return $votes;
    }
}
