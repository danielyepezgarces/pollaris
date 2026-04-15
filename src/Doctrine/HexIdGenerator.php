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

namespace App\Doctrine;

use App\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;

class HexIdGenerator extends AbstractIdGenerator
{
    public function generateId(EntityManagerInterface $entityManager, ?object $entity): string
    {
        if ($entity === null) {
            throw new \LogicException('Entity must not be null');
        }

        while (true) {
            $id = Utils\Random::hex(20);

            if ($entityManager->find($entity::class, $id) === null) {
                return $id;
            }
        }
    }
}
