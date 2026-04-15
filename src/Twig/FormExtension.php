<?php

// This file is part of Pollaris.
// Copyright 2022-2024 Probesys (Bileto)
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Symfony\Component\Form\FormView;
use Twig\Attribute\AsTwigFunction;

class FormExtension
{
    /**
     * @param FormView|string $field
     */
    #[AsTwigFunction('field_id')]
    public function fieldId(mixed $field, string $suffix = ''): string
    {
        if ($field instanceof FormView) {
            $id = $field->vars['id'];
        } else {
            $id = $field;
        }

        if ($suffix) {
            return $id . '-' . $suffix;
        } else {
            return $id;
        }
    }
}
