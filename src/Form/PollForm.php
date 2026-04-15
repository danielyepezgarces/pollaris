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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class PollForm extends AbstractType
{
    use TimezonesConfiguratorTrait;

    public function __construct(
        #[Autowire('%app.require_emails%')]
        private bool $requireEmails,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentUser = $options['current_user'] ?? null;
        
        $builder->add('title', Type\TextType::class, [
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.poll_form.title.label'),
            'attr' => [
                'maxlength' => Entity\Poll::MAX_TITLE_LENGTH,
            ],
        ]);

        $builder->add('description', Type\TextareaType::class, [
            'required' => false,
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.poll_form.description.label'),
            'help' => new TranslatableMessage('forms.poll_form.description.help'),
            'attr' => [
                'rows' => 5,
            ],
        ]);

        $builder->add('localizedDescriptions', Type\CollectionType::class, [
            'entry_type' => LocalizedDescriptionItemType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
            'label' => new TranslatableMessage('forms.poll_form.localized_descriptions.label'),
            'help' => new TranslatableMessage('forms.poll_form.localized_descriptions.help'),
        ]);

        $builder->add('closedAt', Type\DateType::class, [
            'input' => 'datetime_immutable',
            'label' => new TranslatableMessage('forms.poll_form.closed_at.label'),
            'help' => new TranslatableMessage('forms.poll_form.closed_at.help'),
        ]);

        $this->addTimezoneFields($builder);

        $authorNameLabel = $currentUser instanceof Entity\User
            ? 'forms.poll_form.author_name.label.wikimedia'
            : 'forms.poll_form.author_name.label';

        $builder->add('authorName', Type\TextType::class, [
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage($authorNameLabel),
            'disabled' => $currentUser instanceof Entity\User,
            'attr' => $currentUser instanceof Entity\User ? [] : [
                'maxlength' => Entity\Poll::MAX_AUTHOR_NAME_LENGTH,
            ],
        ]);

        $authorEmailOptions = [
            'required' => false,
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.poll_form.author_email.label'),
            'help' => new TranslatableMessage('forms.poll_form.author_email.help'),
            'disabled' => $currentUser instanceof Entity\User,
        ];

        if ($this->requireEmails && !($currentUser instanceof Entity\User)) {
            $authorEmailOptions['required'] = true;
            $authorEmailOptions['constraints'] = [
                new Assert\NotBlank(
                    message: new TranslatableMessage('poll.author_email.required', domain: 'validators'),
                ),
            ];
        }

        $builder->add('authorEmail', Type\EmailType::class, $authorEmailOptions);

        $this->addTimezoneSubmitListener($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'form--standard',
            ],
            'data_class' => Entity\Poll::class,
            'current_user' => null,
        ]);

        $resolver->setAllowedTypes('current_user', ['null', Entity\User::class]);
    }
}
