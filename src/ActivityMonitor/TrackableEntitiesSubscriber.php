<?php

// This file is part of Pollaris.
// Copyright 2022-2024 Probesys (Bileto)
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

namespace App\ActivityMonitor;

use App\Utils;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;

/**
 * Monitor the entities changes to save the createdAt and updatedAt fields.
 * Only entities implementing the TrackableEntityInterface are monitored.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class TrackableEntitiesSubscriber
{
    /**
     * Save all the tracking fields (i.e. createdAt and updatedAt) of the
     * TrackableEntityInterface entities.
     *
     * createdAt isn't changed if these fields are already set.
     * This allows to force a custom value for these fields.
     *
     * updatedAt are always set.
     *
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof TrackableEntityInterface)) {
            return;
        }

        $now = Utils\Time::now();

        if (!$entity->getCreatedAt()) {
            $entity->setCreatedAt($now);
        }

        $entity->setUpdatedAt($now);
    }

    /**
     * Save the tracking field updatedAt of the TrackableEntityInterface
     * entities.
     *
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof TrackableEntityInterface)) {
            return;
        }

        $now = Utils\Time::now();
        $entity->setUpdatedAt($now);
    }
}
