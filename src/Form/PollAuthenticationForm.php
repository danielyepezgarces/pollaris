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
use App\Validator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class PollAuthenticationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', Type\PasswordType::class, [
            'label' => new TranslatableMessage('forms.poll_authentication_form.password.label'),
            'constraints' => [
                new Assert\NotBlank(
                    message: new TranslatableMessage('poll.password.required', domain: 'validators'),
                ),
                new Validator\PollPassword(
                    message: new TranslatableMessage('poll.password.incorrect', domain: 'validators'),
                    poll: $options['poll'],
                ),
            ],
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.poll_authentication_form.submit'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'form--standard',
            ],
            'poll' => null,
        ]);

        $resolver->setAllowedTypes('poll', Entity\Poll::class);
    }
}
