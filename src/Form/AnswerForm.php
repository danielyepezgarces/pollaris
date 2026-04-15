<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use App\Service;
use Doctrine\Common\Collections;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class AnswerForm extends AbstractType
{
    public function __construct(
        private Service\DateTranslator $dateTranslator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $answer = $event->getData();
            $proposal = $answer->getProposal();
            $poll = $proposal->getPoll();

            $date = $proposal->getDate();
            $proposalDate = '';
            if ($date && $date->getValue()) {
                $proposalDate = $this->dateTranslator->format($date->getValue(), 'EEEE d MMMM yyyy');
            }

            $yesDisabled = false;
            $maxVotes = $poll->getMaxVotes();

            if ($maxVotes !== null && $maxVotes > 0) {
                $vote = $answer->getVote();
                $excludeVote = $vote->getId() !== null ? $vote : null;
                $countYes = $proposal->countAnswers('yes', excludeVote: $excludeVote);

                $yesDisabled = $countYes >= $maxVotes;
            }

            if ($poll->isDisableMaybe()) {
                $choices = ['yes', 'no'];
            } else {
                $choices = ['yes', 'maybe', 'no'];
            }

            if ($poll->isVoteNoByDefault()) {
                $defaultValue = 'no';
            } else {
                $defaultValue = null;
            }

            $form->add('value', Type\ChoiceType::class, [
                'choices' => $choices,
                'label' => false,
                'empty_data' => $defaultValue,
                'expanded' => true,
                'required' => false,
                'placeholder' => false,

                'choice_label' => function (string $choice): TranslatableMessage {
                    return Entity\Answer::translateValue($choice);
                },

                'choice_attr' => function (string $choice) use ($yesDisabled): array {
                    $attrs = [
                        'class' => "radio--vote radio--vote-{$choice}",
                        'data-form-leave-confirmation-target' => 'input',
                        'data-form-vote-validation-target' => 'input',
                    ];

                    if ($choice === 'yes' && $yesDisabled) {
                        $attrs['disabled'] = true;
                    }

                    return $attrs;
                },

                'attr' => [
                    'class' => 'vote__choices',
                    'data-proposal-id' => $proposal->getId(),
                    'data-date' => $proposalDate,
                    'data-controller' => 'toggle-radio',
                    'data-action' => 'click->toggle-radio#toggle'
                ],

                'label_attr' => [
                    'class' => 'text--normal',
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Answer::class,
        ]);
    }
}
