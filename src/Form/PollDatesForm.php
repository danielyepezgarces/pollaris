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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class PollDatesForm extends AbstractType
{
    use TimezonesConfiguratorTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addTimezoneFields($builder);

        $builder->add('dates', Type\CollectionType::class, [
            'entry_type' => DateForm::class,
            'entry_options' => [
                'label' => false,
            ],
            'label' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'constraints' => [
                new Assert\Count(
                    min: 1,
                    minMessage: new TranslatableMessage('poll.dates.required', domain: 'validators'),
                ),
            ],
        ]);

        $this->addTimezoneSubmitListener($builder, normalizeDates: true);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'form--standard',
            ],
            'data_class' => Entity\Poll::class,
            'cascade_validation' => true,
        ]);
    }
}
