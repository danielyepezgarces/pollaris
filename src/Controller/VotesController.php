<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use App\Security;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VotesController extends BaseController
{
    public function __construct(
        private readonly Repository\PollRepository $pollRepository,
        private readonly Repository\VoteRepository $voteRepository,
        private readonly Security\PollSecurity $pollSecurity,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/polls/{slug}/votes/{id:vote}/edit', name: 'edit vote')]
    public function edit(
        string $slug,
        Entity\Vote $vote,
        Request $request,
    ): Response {
        $currentUser = $this->getUser();
        $poll = $this->pollRepository->loadBySlug($slug);

        if (!$poll) {
            throw $this->createNotFoundException('The poll doesn’t exist.');
        }

        if ($poll->getId() !== $vote->getPoll()->getId()) {
            throw $this->createNotFoundException('Vote is not part of the poll');
        }

        if (!$this->pollSecurity->isAuthenticated($poll)) {
            return $this->redirectToRoute('authenticate poll', [
                'slug' => $poll->getSlug(),
            ]);
        }

        if (!$this->pollSecurity->canEditVotes($poll)) {
            return $this->redirectToRoute('poll', [
                'slug' => $poll->getSlug(),
            ]);
        }

        if ($currentUser instanceof Entity\User) {
            $vote->setAuthorName($currentUser->getUserIdentifier());
        }

        $form = $this->createNamedForm('vote', Form\VoteForm::class, $vote, [
            'author_name_locked' => $currentUser instanceof Entity\User,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $vote = $form->getData();

            if ($form->isValid()) {
                $this->voteRepository->save($vote);

                $session = $request->getSession();
                $session->set("vote-{$poll->getId()}", $vote->getId());

                $this->addFlash('success', 'vote.updated');
                $this->addFlash('storeMyVote', true);

                return $this->redirectToRoute('poll', [
                    'slug' => $poll->getSlug(),
                ]);
            } else {
                // Reset the vote so it doesn't display the changes in the
                // interface.
                $this->entityManager->refresh($vote);
                $this->entityManager->clear();
            }
        }

        return $this->render('polls/show.html.twig', [
            'poll' => $poll,
            'myVote' => $vote,
            'voteForm' => $form,
            'preserveScroll' => true,
            'commentForm' => null,
            'onEditPage' => true,
        ]);
    }

    #[Route('/polls/{pollId:poll}/{token}/votes/{voteId:vote}/deletion', name: 'delete vote', methods: ['POST'])]
    public function deletion(
        #[MapEntity(mapping: ['poll' => 'id'])]
        Entity\Poll $poll,
        #[MapEntity(mapping: ['vote' => 'id'])]
        Entity\Vote $vote,
        string $token,
        Request $request,
    ): Response {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        if ($poll->getId() !== $vote->getPoll()->getId()) {
            throw $this->createNotFoundException('Vote is not part of the poll');
        }

        $csrfToken = $request->request->getString('_csrf_token', '');

        if ($this->isCsrfTokenValid('delete vote', $csrfToken)) {
            $this->voteRepository->remove($vote, true);
        }

        return $this->redirectToRoute('poll admin', [
            'id' => $poll->getId(),
            'token' => $poll->getAdminToken(),
        ]);
    }
}
