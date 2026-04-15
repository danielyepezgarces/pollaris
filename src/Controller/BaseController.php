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

namespace App\Controller;

use App\Entity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * Returns a rendered 404 Response unless the current user is the poll owner.
     * Returns null when access is allowed.
     */
    protected function denyUnlessPollAdmin(Entity\Poll $poll): ?Response
    {
        $owner = $poll->getOwner();
        $user = $this->getUser();

        if ($user === null || $owner === null || $owner->getUserIdentifier() !== $user->getUserIdentifier()) {
            return $this->render('bundles/TwigBundle/Exception/error404.html.twig', [], new Response('', 404));
        }

        return null;
    }
}
