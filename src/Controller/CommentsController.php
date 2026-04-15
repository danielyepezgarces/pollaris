<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Repository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommentsController extends BaseController
{
    public function __construct(
        private readonly Repository\CommentRepository $commentRepository,
    ) {
    }

    #[Route(
        '/polls/{pollId:poll}/{token}/comments/{commentId:comment}/deletion',
        name: 'delete comment',
        methods: ['POST']
    )]
    public function deletion(
        #[MapEntity(mapping: ['poll' => 'id'])]
        Entity\Poll $poll,
        #[MapEntity(mapping: ['comment' => 'id'])]
        Entity\Comment $comment,
        string $token,
        Request $request,
    ): Response {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        if ($poll->getId() !== $comment->getPoll()->getId()) {
            throw $this->createNotFoundException('Comment is not part of the poll');
        }

        $csrfToken = $request->request->getString('_csrf_token', '');

        if ($this->isCsrfTokenValid('delete comment', $csrfToken)) {
            $this->commentRepository->remove($comment, true);
        }

        return $this->redirectToRoute('poll admin', [
            'id' => $poll->getId(),
            'token' => $poll->getAdminToken(),
        ]);
    }
}
