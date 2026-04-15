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

namespace App\Form;

use App\Entity;
use App\Utils;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Translation\TranslatableMessage;

trait TimezonesConfiguratorTrait
{
    private function addTimezoneFields(FormBuilderInterface $builder): void
    {
        $builder->add('timezoneMode', Type\ChoiceType::class, [
            'choices' => Entity\Poll::TIMEZONE_MODES,
            'label' => new TranslatableMessage('forms.poll_form.timezone_mode.label'),
            'help' => new TranslatableMessage('forms.poll_form.timezone_mode.help', [
                'timezone' => Utils\Time::getServerTimezoneName(),
            ]),
            'expanded' => true,
            'choice_label' => function (string $choice): TranslatableMessage {
                return match ($choice) {
                    'server' => new TranslatableMessage('forms.poll_form.timezone_mode.server', [
                        'timezone' => Utils\Time::getServerTimezoneName(),
                    ]),
                    'browser' => new TranslatableMessage('forms.poll_form.timezone_mode.browser'),
                    default => throw new \LogicException("{$choice} is an invalid choice"),
                };
            },
        ]);

        $builder->add('browserTimezone', Type\HiddenType::class, [
            'mapped' => false,
            'required' => false,
            'attr' => [
                'data-poll-timezone-target' => 'browserTimezone',
            ],
        ]);
    }

    private function addTimezoneSubmitListener(FormBuilderInterface $builder, bool $normalizeDates = false): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($normalizeDates): void {
            $form = $event->getForm();
            $poll = $event->getData();

            if (!$poll instanceof Entity\Poll) {
                return;
            }

            $timezoneName = Utils\Time::resolvePollTimezone(
                $poll->getTimezoneMode(),
                $form->get('browserTimezone')->getData(),
                $poll->getTimezoneName(),
            );

            $poll->setTimezoneName($timezoneName);
            $poll->setClosedAt(Utils\Time::normalizeDateForTimezone($poll->getClosedAt(), $timezoneName));

            if (!$normalizeDates) {
                return;
            }

            foreach ($poll->getDates() as $date) {
                $date->setValue(Utils\Time::normalizeDateForTimezone($date->getValue(), $timezoneName));
            }
        });
    }
}
