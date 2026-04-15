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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig;

class HomeController extends BaseController
{
    public function __construct(
        private readonly Twig\Environment $twig
    ) {
    }

    #[Route('/', name: 'home')]
    public function show(): Response
    {
        $twigLoader = $this->twig->getLoader();
        if ($twigLoader->exists('home/custom.html.twig')) {
            return $this->render('home/custom.html.twig');
        } else {
            return $this->render('home/show.html.twig');
        }
    }
}
