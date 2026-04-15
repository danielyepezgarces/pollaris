<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Utils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class PreferencesForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $languages = Utils\Locales::getSupportedLanguages();
        asort($languages);

        $builder->add('locale', Type\ChoiceType::class, [
            'choices' => array_flip($languages),
            'label' => new TranslatableMessage('forms.preferences.locale.label'),
        ]);

        $builder->add('colorScheme', Type\ChoiceType::class, [
            'choices' => ['auto', 'light', 'dark'],
            'label' => new TranslatableMessage('forms.preferences.color_scheme.label'),
            'choice_label' => function (string $choice): TranslatableMessage {
                if ($choice === 'auto') {
                    return new TranslatableMessage('forms.preferences.color_scheme.auto');
                } elseif ($choice === 'light') {
                    return new TranslatableMessage('forms.preferences.color_scheme.light');
                } elseif ($choice === 'dark') {
                    return new TranslatableMessage('forms.preferences.color_scheme.dark');
                } else {
                    throw new \LogicException("{$choice} is an invalid choice");
                }
            },
            'attr' => [
                'data-color-scheme-target' => 'select',
            ],
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.confirm'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'form--standard',
                'data-action' => 'color-scheme#change',
            ],
        ]);
    }
}
