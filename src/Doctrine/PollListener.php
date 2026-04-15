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

use App\Entity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
class PollListener
{
    /**
     * Synchronize existing votes with new poll's proposals.
     *
     * If a poll with existing votes is changed to add new proposals, the
     * update vote forms will break. Indeed, to build these forms, we need to
     * get the answers for all the poll's proposals. But if we add new
     * proposal, there will be no answers for them.
     *
     * @see \App\Twig\PollExtension::getAnswerFormForProposal
     *
     * To fix that, when proposals are created, we also create empty answers
     * for all the existing votes.
     */
    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entityEvents = [];

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if (!($entity instanceof Entity\Proposal)) {
                return;
            }

            $proposal = $entity;
            $answers = $proposal->buildMissingAnswers();

            foreach ($answers as $answer) {
                $entityManager->persist($answer);

                // This is required by Doctrine in the onFlush event. It likely
                // acts as flushing during a flush.
                $classMetadata = $entityManager->getClassMetadata(Entity\Answer::class);
                $unitOfWork->computeChangeSet($classMetadata, $answer);
            }
        }
    }
}
