<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class CommentForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('authorName', Type\TextType::class, [
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.comment_form.author_name.label'),
            'disabled' => $options['author_name_locked'],
            'attr' => [
                'maxlength' => Entity\Comment::MAX_AUTHOR_NAME_LENGTH,
            ],
        ]);

        $builder->add('content', Type\TextareaType::class, [
            'trim' => true,
            'empty_data' => '',
            'label' => new TranslatableMessage('forms.comment_form.content.label'),
            'attr' => [
                'rows' => 3,
            ],
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.comment_form.submit'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'author_name_locked' => false,
            'attr' => [
                'class' => 'form--standard',
            ],
            'data_class' => Entity\Comment::class,
        ]);

        $resolver->setAllowedTypes('author_name_locked', 'bool');
    }
}
