<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Entity\CommunityMember>
 */
class CommunityMemberRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity\CommunityMember::class);
    }

    public function findMembership(Entity\Community $community, Entity\User $user): ?Entity\CommunityMember
    {
        return $this->findOneBy([
            'community' => $community,
            'user' => $user,
        ]);
    }

    /**
     * @return Entity\CommunityMember[]
     */
    public function findActiveMembers(Entity\Community $community): array
    {
        return $this->findBy([
            'community' => $community,
            'status' => Entity\CommunityMember::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return Entity\CommunityMember[]
     */
    public function findPendingMembers(Entity\Community $community): array
    {
        return $this->findBy([
            'community' => $community,
            'status' => Entity\CommunityMember::STATUS_PENDING,
        ], ['createdAt' => 'ASC']);
    }
}
