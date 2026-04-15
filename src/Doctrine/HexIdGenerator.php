<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

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
