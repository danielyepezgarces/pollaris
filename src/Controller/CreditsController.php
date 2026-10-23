<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Service\TranslationCreditsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CreditsController extends BaseController
{
    public function __construct(
        private readonly TranslationCreditsService $creditsService,
    ) {
    }

    #[Route('/credits', name: 'credits')]
    #[Route('/credits.json', name: 'credits_json')]
    public function show(Request $request): Response
    {
        $currentLocale = $request->getLocale();
        $translators = $this->creditsService->getTranslators($currentLocale);
        $stats = $this->creditsService->getStats($currentLocale);

        if ($request->getRequestFormat() === 'json' || $request->attributes->get('_route') === 'credits_json' || $request->query->get('format') === 'json') {
            return $this->json([
                'stats' => $stats,
                'translators' => array_values($translators),
            ]);
        }

        return $this->render('credits/show.html.twig', [
            'translators' => $translators,
            'stats' => $stats,
        ]);
    }
}
