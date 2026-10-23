<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class CommunityForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', Type\TextType::class, [
                'label' => new TranslatableMessage('forms.community_form.name.label'),
                'help' => new TranslatableMessage('forms.community_form.name.help'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('slug', Type\TextType::class, [
                'label' => new TranslatableMessage('forms.community_form.slug.label'),
                'help' => new TranslatableMessage('forms.community_form.slug.help'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 100),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-Z0-9_-]+$/',
                        'message' => new TranslatableMessage('forms.community_form.slug.pattern'),
                    ]),
                ],
            ])
            ->add('description', Type\TextareaType::class, [
                'required' => false,
                'label' => new TranslatableMessage('forms.community_form.description.label'),
                'help' => new TranslatableMessage('forms.community_form.description.help'),
            ])
            ->add('joinPolicy', Type\ChoiceType::class, [
                'label' => new TranslatableMessage('forms.community_form.join_policy.label'),
                'choices' => [
                    'forms.community_form.join_policy.approval' => Entity\Community::JOIN_POLICY_APPROVAL,
                    'forms.community_form.join_policy.open' => Entity\Community::JOIN_POLICY_OPEN,
                ],
                'choice_label' => fn ($choice, $key, $value) => new TranslatableMessage($key),
                'expanded' => true,
            ])
            ->add('emailEnabled', Type\CheckboxType::class, [
                'required' => false,
                'label' => new TranslatableMessage('forms.community_form.email_enabled.label'),
                'help' => new TranslatableMessage('forms.community_form.email_enabled.help'),
            ])
            ->add('telegramEnabled', Type\CheckboxType::class, [
                'required' => false,
                'label' => new TranslatableMessage('forms.community_form.telegram_enabled.label'),
                'help' => new TranslatableMessage('forms.community_form.telegram_enabled.help'),
            ])
            ->add('telegramChatId', Type\TextType::class, [
                'required' => false,
                'label' => new TranslatableMessage('forms.community_form.telegram_chat_id.label'),
                'help' => new TranslatableMessage('forms.community_form.telegram_chat_id.help'),
            ])
            ->add('submit', Type\SubmitType::class, [
                'label' => new TranslatableMessage('forms.save'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Community::class,
        ]);
    }
}
