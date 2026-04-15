<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Adrien Scholaert <adrien@framasoft.org>
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Entity\Poll>
 */
class PollRepository extends BaseRepository
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Entity\Poll::class);
    }

    public function loadBySlug(string $slug): ?Entity\Poll
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT p, pr, d
            FROM App\Entity\Poll p
            LEFT JOIN p.proposals pr
            LEFT JOIN p.dates d
            WHERE p.slug = :slug
        SQL);

        $query->setParameter('slug', $slug);

        return $query->getOneOrNullResult();
    }

    /**
     * @return ORM\Query<null, Entity\Poll>
     */
    public function getSearchQuery(
        string $search,
        ?Entity\User $owner = null,
        bool $includeAllPolls = false,
    ): ORM\Query
    {
        $queryBuilder = $this->createQueryBuilder('p');

        if ($owner !== null && !$includeAllPolls) {
            $queryBuilder
                ->andWhere('p.owner = :owner')
                ->setParameter('owner', $owner);
        }

        $search = trim($search);

        $pollBaseUrl = $this->urlGenerator->generate('home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        $pollBaseUrl = "{$pollBaseUrl}polls/";

        if (str_starts_with($search, $pollBaseUrl)) {
            $pollSlug = substr($search, strlen($pollBaseUrl));

            $remainingSlashPosition = strpos($pollSlug, '/');
            if ($remainingSlashPosition !== false) {
                $pollSlug = substr($pollSlug, 0, $remainingSlashPosition);
            }

            $queryBuilder->where('p.slug = :slug');
            $queryBuilder->orWhere('p.id = :slug');
            $queryBuilder->setParameter('slug', $pollSlug);
        } elseif ($search !== '') {
            $queryBuilder->where('ILIKE(p.authorEmail, :query) = TRUE');
            $queryBuilder->orWhere('ILIKE(p.authorName, :query) = TRUE');
            $queryBuilder->orWhere('ILIKE(p.title, :query) = TRUE');
            $queryBuilder->orWhere('p.slug = :slug');
            $queryBuilder->setParameter('query', "%{$search}%");
            $queryBuilder->setParameter('slug', $search);
        }

        $queryBuilder->orderBy('p.createdAt', 'DESC');

        return $queryBuilder->getQuery();
    }

    public function deleteExpiredPolls(
        \DateTimeImmutable $completedExpirationDate,
        \DateTimeImmutable $incompleteExpirationDate,
    ): int {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            DELETE App\Entity\Poll p
            WHERE (p.completedAt IS NOT NULL AND p.closedAt <= :completedExpirationDate)
            OR (p.completedAt IS NULL AND p.closedAt <= :incompleteExpirationDate)
        SQL);

        $query->setParameter('completedExpirationDate', $completedExpirationDate);
        $query->setParameter('incompleteExpirationDate', $incompleteExpirationDate);

        return $query->execute();
    }

    public function countExpiredPolls(
        \DateTimeImmutable $completedExpirationDate,
        \DateTimeImmutable $incompleteExpirationDate,
    ): int {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT COUNT(p)
            FROM App\Entity\Poll p
            WHERE (p.completedAt IS NOT NULL AND p.closedAt <= :completedExpirationDate)
            OR (p.completedAt IS NULL AND p.closedAt <= :incompleteExpirationDate)
        SQL);

        $query->setParameter('completedExpirationDate', $completedExpirationDate);
        $query->setParameter('incompleteExpirationDate', $incompleteExpirationDate);

        return (int) $query->getSingleScalarResult();
    }
}
