<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

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
