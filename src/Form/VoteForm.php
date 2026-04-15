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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

class VoteForm extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('authorName', Type\TextType::class, [
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.vote_form.author_name.label'),
            'disabled' => $options['author_name_locked'],
            'attr' => $options['author_name_locked'] ? [
                'data-form-leave-confirmation-target' => 'input',
            ] : [
                'maxlength' => Entity\Vote::MAX_AUTHOR_NAME_LENGTH,
                'data-form-leave-confirmation-target' => 'input',
            ],
        ]);

        $builder->add('answers', Type\CollectionType::class, [
            'entry_type' => AnswerForm::class,
            'entry_options' => [
                'label' => false,
            ],
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.vote_form.submit.label'),
            'attr' => [
                'class' => 'button--primary',
                'data-form-vote-validation-target' => 'submitButton',
            ],
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $vote = $event->getData();

            $poll = $vote->getPoll();

            if ($poll->isVotePasswordProtected()) {
                $form->add('password', Type\PasswordType::class, [
                    'label' => new TranslatableMessage('forms.vote_form.password.label'),
                    'help' => new TranslatableMessage('forms.vote_form.password.help'),
                    'mapped' => false,
                    'constraints' => [
                        new Assert\NotBlank(
                            message: new TranslatableMessage('poll.password.required', domain: 'validators'),
                        ),
                        new Validator\PollPassword(
                            message: new TranslatableMessage('poll.password.incorrect', domain: 'validators'),
                            poll: $poll,
                        ),
                    ],
                ]);
            }

            if (count($vote->getAnswers()) > 0) {
                return;
            }

            $proposals = $poll->getProposals();

            foreach ($proposals as $proposal) {
                $answer = new Entity\Answer();
                $answer->setProposal($proposal);
                $vote->addAnswer($answer);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $leaveConfirmation = $this->translator->trans('forms.vote_form.leave_confirmation');
        $requiredChoice = $this->translator->trans('forms.vote_form.required_choice');

        $resolver->setDefaults([
            'author_name_locked' => false,
            'data_class' => Entity\Vote::class,
            'attr' => [
                'data-controller' => 'form-leave-confirmation form-vote-validation',
                'data-action' => (
                    'form-leave-confirmation#disableCheck'
                    . ' beforeunload@window->form-leave-confirmation#check'
                    . ' turbo:before-visit@window->form-leave-confirmation#check'
                    . ' submit->form-vote-validation#onSubmit'
                    . ' change->form-vote-validation#onChange'
                ),
                'data-form-leave-confirmation-confirmation-value' => $leaveConfirmation,
                'data-form-vote-validation-required-choice-value' => $requiredChoice,
            ],
        ]);

        $resolver->setAllowedTypes('author_name_locked', 'bool');
    }
}
