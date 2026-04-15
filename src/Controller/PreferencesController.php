<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Form;
use App\Utils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PreferencesController extends BaseController
{
    #[Route('/preferences', name: 'edit preferences')]
    public function edit(Request $request): Response
    {
        $referer = $request->headers->get('Referer');
        if ($referer === null) {
            $referer = '/';
        }

        $form = $this->createNamedForm('preferences', Form\PreferencesForm::class, [
            'locale' => $request->getLocale(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $locale = $form->get('locale')->getData();

            $session = $request->getSession();
            $session->set('_locale', $locale);

            return $this->redirect($referer);
        }

        return $this->render('preferences/edit.html.twig', [
            'form' => $form,
        ]);
    }
}
