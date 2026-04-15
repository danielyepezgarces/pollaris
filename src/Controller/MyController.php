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
use App\Repository;
use App\Service;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MyController extends BaseController
{
    public function __construct(
        private readonly Service\PollsFinder $pollsFinder,
        private readonly Repository\PollRepository $pollRepository,
        private readonly Repository\VoteRepository $voteRepository,
    ) {
    }

    #[Route('/my', name: 'my')]
    public function index(Request $request): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof Entity\User) {
            return $this->render('my/index.html.twig', [
                'ownedPolls' => [],
                'userVotes' => [],
                'searchForm' => null,
            ]);
        }

        $ownedPolls = $this->pollRepository
            ->getSearchQuery('', $currentUser, false)
            ->getResult();
        
        // Get user's votes: owned + unclaimed votes with matching authorName (legacy)
        $userVotes = $this->voteRepository->findAllByUser($currentUser);

        $searchForm = $this->createNamedForm('search_polls', Form\SearchPollsForm::class);

        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $email = $searchForm->get('email')->getData();

            $this->pollsFinder->sendEmailLinks($email);

            return $this->redirectToRoute('my', [
                'mailSent' => true,
            ]);
        }

        return $this->render('my/index.html.twig', [
            'ownedPolls' => $ownedPolls,
            'userVotes' => $userVotes,
            'searchForm' => $searchForm,
        ]);
    }
}
