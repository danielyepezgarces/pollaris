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

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @template T of object
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class BaseRepository extends ServiceEntityRepository
{
    /**
     * @param T|T[] $entities
     */
    public function save(mixed $entities, bool $flush = true): void
    {
        if (!is_array($entities)) {
            $entities = [$entities];
        }

        $entityManager = $this->getEntityManager();

        foreach ($entities as $entity) {
            $entityManager->persist($entity);
        }

        if ($flush) {
            $entityManager->flush();
        }
    }

    /**
     * @param T|T[] $entities
     */
    public function remove(mixed $entities, bool $flush = true): void
    {
        if (!is_array($entities)) {
            $entities = [$entities];
        }

        $entityManager = $this->getEntityManager();

        foreach ($entities as $entity) {
            $entityManager->remove($entity);
        }

        if ($flush) {
            $entityManager->flush();
        }
    }

    /**
     * @param T $entity
     */
    public function refresh(object $entity): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->refresh($entity);
    }
}
