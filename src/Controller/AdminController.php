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
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Page not found.');
        }

        $page = $request->query->getInt('page', 1);
        $search = $request->query->getString('q', '');
        $currentUser = $this->getUser();
        $includeAllPolls = true;

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
