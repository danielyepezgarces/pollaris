<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
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
            'attr' => [
                'rows' => 5,
            ],
        ]);

        $builder->add('closedAt', Type\DateType::class, [
            'input' => 'datetime_immutable',
            'label' => new TranslatableMessage('forms.poll_form.closed_at.label'),
            'help' => new TranslatableMessage('forms.poll_form.closed_at.help'),
        ]);

        $this->addTimezoneFields($builder);

        $builder->add('authorName', Type\TextType::class, [
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.poll_form.author_name.label'),
            'attr' => [
                'maxlength' => Entity\Poll::MAX_AUTHOR_NAME_LENGTH,
            ],
        ]);

        $authorEmailOptions = [
            'required' => false,
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.poll_form.author_email.label'),
            'help' => new TranslatableMessage('forms.poll_form.author_email.help'),
        ];

        if ($this->requireEmails) {
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
        ]);
    }
}
