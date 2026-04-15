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
use App\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class PollSettingsForm extends AbstractType
{
    public function __construct(
        private Service\PollPassword $pollPassword,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('disableMaybe', Type\CheckboxType::class, [
            'label' => new TranslatableMessage('forms.poll_settings_form.disable_maybe.label'),
            'required' => false,
        ]);

        $builder->add('voteNoByDefault', Type\CheckboxType::class, [
            'label' => new TranslatableMessage('forms.poll_settings_form.vote_no_by_default.label'),
            'required' => false,
        ]);

        $builder->add('maxVotes', Type\IntegerType::class, [
            'label' => new TranslatableMessage('forms.poll_settings_form.max_votes.label'),
            'required' => false,
        ]);

        $pollBaseUrl = $this->urlGenerator->generate('home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        $pollBaseUrl = "{$pollBaseUrl}polls/";

        $builder->add('slug', Type\TextType::class, [
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.poll_settings_form.slug.label'),
            'help' => new TranslatableMessage('forms.poll_settings_form.slug.help'),
            'attr' => [
                'maxlength' => Entity\Poll::MAX_SLUG_LENGTH,
                'data-prefix' => $pollBaseUrl,
            ],
            'constraints' => [
                new Assert\NotBlank(
                    message: new TranslatableMessage('poll.slug.required', domain: 'validators'),
                )
            ],
            'block_prefix' => 'urlprefix',
        ]);

        $builder->add('isPasswordProtected', Type\CheckboxType::class, [
            'label' => new TranslatableMessage('forms.poll_settings_form.password.protect'),
            'required' => false,
            'mapped' => false,
            'attr' => [
                'data-poll-password-target' => 'isPasswordProtected',
                'data-action' => 'poll-password#refresh',
            ],
        ]);

        $builder->add('isPasswordForVotesOnly', Type\CheckboxType::class, [
            'label' => new TranslatableMessage('forms.poll_settings_form.password_for_votes_only.label'),
            'required' => false,
            'attr' => [
                'data-poll-password-target' => 'isPasswordForVotesOnly',
            ],
        ]);

        $builder->add('editVoteMode', Type\ChoiceType::class, [
            'choices' => Entity\Poll::EDIT_VOTE_MODES,
            'label' => false,
            'empty_data' => 'no',
            'expanded' => true,
            'choice_label' => function (string $choice): TranslatableMessage {
                return Entity\Poll::translateEditVoteMode($choice);
            },
        ]);

        $builder->add('areResultsPublic', Type\CheckboxType::class, [
            'label' => new TranslatableMessage('forms.poll_settings_form.are_results_public.label'),
            'required' => false,
        ]);

        $builder->add('notifyOnVotes', Type\CheckboxType::class, [
            'label' => new TranslatableMessage('forms.poll_settings_form.notify_on_votes.label'),
            'required' => false,
        ]);

        $builder->add('notifyOnComments', Type\CheckboxType::class, [
            'label' => new TranslatableMessage('forms.poll_settings_form.notify_on_comments.label'),
            'required' => false,
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $poll = $event->getData();

            $plainPasswordOptions = [
                'type' => Type\PasswordType::class,
                'first_options'  => [
                    'label' => new TranslatableMessage('forms.poll_settings_form.password.label'),
                    'attr' => [
                        'data-poll-password-target' => 'firstPlainPassword',
                    ],
                ],
                'second_options' => [
                    'label' => new TranslatableMessage('forms.poll_settings_form.repeat_password.label'),
                    'attr' => [
                        'data-poll-password-target' => 'secondPlainPassword',
                    ],
                ],
                'mapped' => false,
            ];

            $isRequired = !$poll->isPasswordProtected();

            if ($isRequired) {
                $plainPasswordOptions['required'] = true;
            } else {
                $plainPasswordOptions['required'] = false;
                $help = new TranslatableMessage('forms.poll_settings_form.password.leave_empty_to_keep');
                $plainPasswordOptions['first_options']['help'] = $help;
            }

            $form->add('plainPassword', Type\RepeatedType::class, $plainPasswordOptions);
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $poll = $event->getData();

            if ($poll->isPasswordProtected()) {
                $form->get('isPasswordProtected')->setData(true);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $poll = $event->getData();

            $isPasswordProtected = $form->get('isPasswordProtected')->getData();
            $plainPassword = $form->get('plainPassword')->getData();

            if ($isPasswordProtected && $plainPassword) {
                $hashedPassword = $this->pollPassword->hash($plainPassword);
                $poll->setPassword($hashedPassword);
            } elseif (!$isPasswordProtected) {
                $poll->setPassword('');
                $poll->setIsPasswordForVotesOnly(false);
            }
        });
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
