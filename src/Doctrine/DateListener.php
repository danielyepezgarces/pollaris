<?php

// This file is part of Pollaris.
// Copyright 2026 Adrien Scholaert
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Doctrine;

use App\Entity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsDoctrineListener(event: Events::prePersist)]
class DateListener
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $entity = $args->getObject();

        if (!($entity instanceof Entity\Date)) {
            return;
        }

        $date = $entity;

        if (!$date->getProposals()->isEmpty() || !$date->getPoll()) {
            return;
        }

        $poll = $date->getPoll();
        $label = $this->translator->trans('forms.slots_form.day', locale: $poll->getLocale());

        $proposal = new Entity\Proposal();
        $proposal->setLabel($label);
        $proposal->setPoll($poll);

        $date->addProposal($proposal);

        $answers = $proposal->buildMissingAnswers();
        foreach ($answers as $answer) {
            $entityManager->persist($answer);
        }
    }
}
