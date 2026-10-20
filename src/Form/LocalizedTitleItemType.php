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

class LocalizedTitleItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('locale', Type\ChoiceType::class, [
                'choices' => array_flip(LocalizedDescriptionItemType::SUPPORTED_LOCALES),
                'label' => new TranslatableMessage('forms.localized_title_item.locale.label'),
                'attr' => ['class' => 'select--small'],
            ])
            ->add('text', Type\TextType::class, [
                'required' => false,
                'trim' => true,
                'empty_data' => '',
                'label' => new TranslatableMessage('forms.localized_title_item.text.label'),
                'attr' => [
                    'maxlength' => Entity\Poll::MAX_TITLE_LENGTH,
                    'autocomplete' => 'off',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
