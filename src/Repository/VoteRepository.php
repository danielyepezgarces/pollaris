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

    public function findOneByOwnerAndPoll(Entity\User $owner, Entity\Poll $poll): ?Entity\Vote
    {
        return $this->findOneBy([
            'owner' => $owner,
            'poll' => $poll,
        ]);
    }

    /**
     * Find an unowned vote by author name and poll (for vote claiming on login).
     */
    public function findOneUnclaimedByAuthorNameAndPoll(string $authorName, Entity\Poll $poll): ?Entity\Vote
    {
        return $this->createQueryBuilder('v')
            ->where('v.poll = :poll')
            ->andWhere('v.owner IS NULL')
            ->andWhere('LOWER(v.authorName) = LOWER(:authorName)')
            ->setParameter('poll', $poll)
            ->setParameter('authorName', $authorName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all votes for a user: owned votes + unowned votes with matching authorName.
     *
     * @return Entity\Vote[]
     */
    public function findAllByUser(Entity\User $user): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.owner = :user')
            ->orWhere('v.owner IS NULL AND LOWER(v.authorName) = LOWER(:username)')
            ->setParameter('user', $user)
            ->setParameter('username', $user->getUserIdentifier())
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
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
