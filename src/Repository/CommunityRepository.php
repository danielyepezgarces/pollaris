<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Entity\Community>
 */
class CommunityRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity\Community::class);
    }

    public function findOneBySlug(string $slug): ?Entity\Community
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findOneByInviteToken(string $token): ?Entity\Community
    {
        return $this->findOneBy(['inviteToken' => $token]);
    }

    /**
     * @return Entity\Community[]
     */
    public function findForUser(Entity\User $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.members', 'm')
            ->where('c.owner = :user')
            ->orWhere('(m.user = :user AND m.status = :activeStatus)')
            ->setParameter('user', $user)
            ->setParameter('activeStatus', Entity\CommunityMember::STATUS_ACTIVE)
            ->orderBy('c.name', 'ASC')
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}
