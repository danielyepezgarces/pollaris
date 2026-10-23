<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class CommunityAddMemberForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', Type\TextType::class, [
                'label' => new TranslatableMessage('forms.community_add_member_form.username.label'),
                'help' => new TranslatableMessage('forms.community_add_member_form.username.help'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('role', Type\ChoiceType::class, [
                'label' => new TranslatableMessage('forms.community_add_member_form.role.label'),
                'choices' => [
                    'forms.community_add_member_form.role.member' => Entity\CommunityMember::ROLE_MEMBER,
                    'forms.community_add_member_form.role.admin' => Entity\CommunityMember::ROLE_ADMIN,
                ],
                'choice_label' => fn ($choice, $key, $value) => new TranslatableMessage($key),
            ])
            ->add('submit', Type\SubmitType::class, [
                'label' => new TranslatableMessage('forms.community_add_member_form.submit'),
            ]);
    }
}
