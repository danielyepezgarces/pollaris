<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;

class BaseController extends AbstractController
{
    /**
     * @param array<string, mixed> $options
     */
    protected function createNamedForm(
        string $name,
        string $type,
        mixed $data = null,
        array $options = [],
    ): FormInterface {
        return $this->container->get('form.factory')->createNamed($name, $type, $data, $options);
    }
}
