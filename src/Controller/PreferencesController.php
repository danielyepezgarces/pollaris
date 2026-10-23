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
use App\Form;
use App\Utils;
use App\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PreferencesController extends BaseController
{
    public function __construct(
        private Service\Referrer $referrerService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/preferences', name: 'edit preferences')]
    public function edit(Request $request): Response
    {
        /** @var Entity\User|null $user */
        $user = $this->getUser();
        $initialLocale = ($user instanceof Entity\User && $user->getLocale())
            ? $user->getLocale()
            : $request->getLocale();

        $form = $this->createNamedForm('preferences', Form\PreferencesForm::class, [
            'locale' => $initialLocale,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $locale = $form->get('locale')->getData();

            $session = $request->getSession();
            $session->set('_locale', $locale);

            if ($user instanceof Entity\User) {
                $user->setLocale($locale);
                $this->entityManager->flush();
            }

            $referrer = $this->referrerService->get();
            return $this->redirect($referrer);
        }

        return $this->render('preferences/edit.html.twig', [
            'form' => $form,
        ]);
    }
}
