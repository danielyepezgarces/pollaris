<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Repository;
use App\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends BaseController
{
    public function __construct(
        private readonly Repository\PollRepository $pollRepository
    ) {
    }

    #[Route('/admin', name: 'admin')]
    public function show(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $search = $request->query->getString('q', '');
        $currentUser = $this->getUser();
        $includeAllPolls = $this->isGranted('ROLE_ADMIN');

        if (!$includeAllPolls && !$currentUser instanceof Entity\User) {
            throw $this->createAccessDeniedException('You must be authenticated to access this page.');
        }

        $searchQuery = $this->pollRepository->getSearchQuery(
            $search,
            $currentUser instanceof Entity\User ? $currentUser : null,
            $includeAllPolls,
        );
        /** @var Utils\Pagination<Entity\Poll> */
        $pollsPagination = Utils\Pagination::paginate($searchQuery, $page);

        return $this->render('admin/show.html.twig', [
            'pollsPagination' => $pollsPagination,
            'search' => $search,
            'includeAllPolls' => $includeAllPolls,
        ]);
    }
}
