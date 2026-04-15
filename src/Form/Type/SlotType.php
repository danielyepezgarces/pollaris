<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class SlotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', Type\TimeType::class, [
            'widget' => 'single_text',
            'input'  => 'string',
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.slot_type.label.label_pattern'),
            'attr' => [
                'maxlength' => Entity\Proposal::MAX_LABEL_LENGTH,
            ],
        ]);

        $builder->get('label')
            ->addModelTransformer(new \Symfony\Component\Form\CallbackTransformer(
                function ($labelFromDatabase) {
                    // If it's an empty string or invalid time format from old data, feed null to TimeType
                    if ($labelFromDatabase === null || $labelFromDatabase === '') {
                        return null;
                    }
                    if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $labelFromDatabase)) {
                        return null;
                    }
                    return $labelFromDatabase;
                },
                function ($labelFromForm) {
                    // If TimeType sends back null, persist it as an empty string for the entity
                    return $labelFromForm ?? '';
                }
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Proposal::class,
        ]);
    }
}
