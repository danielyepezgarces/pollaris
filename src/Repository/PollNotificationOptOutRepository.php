<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Entity\PollNotificationOptOut>
 */
class PollNotificationOptOutRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity\PollNotificationOptOut::class);
    }

    public function isOptedOut(Entity\Poll $poll, ?Entity\User $user = null, ?string $email = null): bool
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.poll = :poll')
            ->setParameter('poll', $poll);

        $conditions = [];
        if ($user !== null) {
            $conditions[] = 'o.user = :user';
            $qb->setParameter('user', $user);
        }
        if ($email !== null && $email !== '') {
            $conditions[] = 'o.email = :email';
            $qb->setParameter('email', $email);
        }

        if (empty($conditions)) {
            return false;
        }

        $qb->andWhere('(' . implode(' OR ', $conditions) . ')');

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
