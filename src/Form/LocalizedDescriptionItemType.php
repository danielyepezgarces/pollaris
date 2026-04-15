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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class LocalizedDescriptionItemType extends AbstractType
{
    /** Locales supported by Chrome's built-in Translator API (BCP 47) */
    public const SUPPORTED_LOCALES = [
        'ar' => 'العربية',
        'bn' => 'বাংলা',
        'cs' => 'Čeština',
        'da' => 'Dansk',
        'de' => 'Deutsch',
        'el' => 'Ελληνικά',
        'en' => 'English',
        'es' => 'Español',
        'fi' => 'Suomi',
        'fr' => 'Français',
        'hi' => 'हिन्दी',
        'hu' => 'Magyar',
        'id' => 'Bahasa Indonesia',
        'it' => 'Italiano',
        'ja' => '日本語',
        'ko' => '한국어',
        'ms' => 'Bahasa Melayu',
        'nl' => 'Nederlands',
        'no' => 'Norsk',
        'pl' => 'Polski',
        'pt' => 'Português',
        'ro' => 'Română',
        'ru' => 'Русский',
        'sk' => 'Slovenčina',
        'sl' => 'Slovenščina',
        'sv' => 'Svenska',
        'th' => 'ภาษาไทย',
        'tr' => 'Türkçe',
        'uk' => 'Українська',
        'vi' => 'Tiếng Việt',
        'zh' => '中文 (简体)',
        'zh-Hant' => '中文 (繁體)',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('locale', Type\ChoiceType::class, [
                'choices' => array_flip(self::SUPPORTED_LOCALES),
                'label' => new TranslatableMessage('forms.localized_description_item.locale.label'),
                'attr' => ['class' => 'select--small'],
            ])
            ->add('text', Type\TextareaType::class, [
                'required' => false,
                'trim' => true,
                'empty_data' => '',
                'label' => new TranslatableMessage('forms.localized_description_item.text.label'),
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
