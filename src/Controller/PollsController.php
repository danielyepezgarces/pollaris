<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Flow;
use App\Form;
use App\PollActivity;
use App\Repository;
use App\Security;
use App\Service;
use App\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Contracts\Translation\TranslatorInterface;

class PollsController extends BaseController
{
    public function __construct(
        private readonly Repository\PollRepository $pollRepository,
        private readonly Repository\VoteRepository $voteRepository,
        private readonly Repository\CommentRepository $commentRepository,
        private readonly Flow\PollFlowBuilder $pollFlowBuilder,
        private readonly Security\PollSecurity $pollSecurity,
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('/polls/choose', name: 'choose poll type')]
    public function choose(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('polls/choose.html.twig');
    }

    #[Route('/polls/new', name: 'new poll')]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $type = $request->query->getString('type');

        if (!in_array($type, Entity\Poll::TYPES)) {
            $type = Entity\Poll::DEFAULT_TYPE;
        }

        $poll = new Entity\Poll();
        $poll->setLocale($request->getLocale());
        $poll->setType($type);

        $currentUser = $this->getUser();
        if ($currentUser instanceof Entity\User) {
            $poll->setOwner($currentUser);

            if ($poll->getAuthorName() === '') {
                $poll->setAuthorName($currentUser->getDisplayName());
            }

            if ($poll->getAuthorEmail() === '' && $currentUser->getEmail()) {
                $poll->setAuthorEmail($currentUser->getEmail());
            }
        }

        $flow = $this->pollFlowBuilder->build($poll);

        $form = $this->createNamedForm('poll', Form\PollForm::class, $poll);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $poll = $form->getData();

            $this->pollRepository->save($poll);

            return $this->redirect($flow->getNextStepUrl('init'));
        }

        return $this->render('polls/new.html.twig', [
            'poll' => $poll,
            'form' => $form,
            'flow' => $flow,
        ]);
    }

    #[Route('/polls/{slug}.csv', name: 'poll csv')]
    public function showCsv(string $slug): Response
    {
        $poll = $this->pollRepository->loadBySlug($slug);

        if (!$poll || !$poll->isCompleted()) {
            throw $this->createNotFoundException('The poll doesn’t exist (yet).');
        }

        if (!$this->pollSecurity->isAuthenticated($poll)) {
            return $this->redirectToRoute('authenticate poll', [
                'slug' => $poll->getSlug(),
            ]);
        }

        if (!$this->pollSecurity->canViewResults($poll)) {
            throw $this->createNotFoundException('You cannot see the results of this poll.');
        }

        $data = [];

        if ($poll->isDatePoll()) {
            $proposalsByDates = $poll->getProposalsByDates();
            $allProposals = [];

            $rowDate = [''];

            foreach ($proposalsByDates as $dateIso => $dateAndProposals) {
                foreach ($dateAndProposals[1] as $proposal) {
                    $rowDate[] = $dateIso;
                    $allProposals[] = $proposal;
                }
            }

            $data[] = $rowDate;
        } else {
            $allProposals = $poll->getProposals();
        }

        $rowProposals = [''];

        foreach ($allProposals as $proposal) {
            $rowProposals[] = $proposal->getLabel();
        }

        $data[] = $rowProposals;

        foreach ($poll->getVotes() as $vote) {
            $voteRow = [$vote->getAuthorName()];

            foreach ($allProposals as $proposal) {
                $answer = $vote->getAnswerForProposal($proposal);
                if ($answer && $answer->getValue()) {
                    $voteRow[] = $this->translator->trans($answer->getHumanValue());
                } else {
                    $voteRow[] = '';
                }
            }

            $data[] = $voteRow;
        }

        $csvEncoder = new CsvEncoder();

        $csv = $csvEncoder->encode($data, 'csv', [
            'csv_escape_formulas' => true,
            'no_headers' => true,
        ]);

        $filename = $poll->getTitle() ?? '';
        $filename = str_replace(' ', '_', $filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        if ($filename === null) {
            $filename = $poll->getSlug() ?? '';
        }
        $filename = preg_replace('/__+/', '_', $filename);
        if ($filename === null) {
            $filename = $poll->getSlug() ?? '';
        }
        $filename = trim($filename, '_') . '.csv';

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    #[Route('/polls/{slug}', name: 'poll')]
    public function show(string $slug, Request $request): Response
    {
        $poll = $this->pollRepository->loadBySlug($slug);

        if (!$poll || !$poll->isCompleted()) {
            throw $this->createNotFoundException('The poll doesn’t exist (yet).');
        }

        if (!$this->pollSecurity->isAuthenticated($poll)) {
            return $this->redirectToRoute('authenticate poll', [
                'slug' => $poll->getSlug(),
            ]);
        }

        $myVote = null;
        $voteForm = null;
        $commentForm = null;
        $currentUser = $this->getUser();

        if (!$poll->isClosed()) {
            $session = $request->getSession();
            $voteId = $session->get("vote-{$poll->getId()}");

            if ($voteId) {
                $myVote = $this->voteRepository->find($voteId);
            }

            if ($currentUser instanceof Entity\User) {
                $vote = new Entity\Vote();
                $vote->setPoll($poll);
                $vote->setAuthorName($currentUser->getUserIdentifier());

                $voteForm = $this->createNamedForm('vote', Form\VoteForm::class, $vote, [
                    'author_name_locked' => true,
                ]);

                $voteForm->handleRequest($request);
                if ($voteForm->isSubmitted() && $voteForm->isValid()) {
                    $vote = $voteForm->getData();

                    $this->voteRepository->save($vote);

                    $voteEvent = new PollActivity\VoteEvent($vote);
                    $this->eventDispatcher->dispatch($voteEvent, PollActivity\VoteEvent::NEW);

                    $session = $request->getSession();
                    $session->set("vote-{$poll->getId()}", $vote->getId());

                    $this->addFlash('success', 'vote.created');
                    $this->addFlash('storeMyVote', true);

                    return $this->redirectToRoute('poll', [
                        'slug' => $poll->getSlug(),
                    ]);
                }

                $comment = new Entity\Comment();
                $comment->setPoll($poll);
                $comment->setAuthorName($currentUser->getUserIdentifier());
                $commentForm = $this->createNamedForm('comment', Form\CommentForm::class, $comment, [
                    'author_name_locked' => true,
                ]);

                $commentForm->handleRequest($request);
                if ($commentForm->isSubmitted() && $commentForm->isValid()) {
                    $comment = $commentForm->getData();

                    $this->commentRepository->save($comment);

                    $commentEvent = new PollActivity\CommentEvent($comment);
                    $this->eventDispatcher->dispatch($commentEvent, PollActivity\CommentEvent::NEW);

                    $this->addFlash('success', 'comment.created');

                    return $this->redirectToRoute('poll', [
                        'slug' => $poll->getSlug(),
                    ]);
                }
            }
        }

        return $this->render('polls/show.html.twig', [
            'poll' => $poll,
            'myVote' => $myVote,
            'voteForm' => $voteForm,
            'commentForm' => $commentForm,
            'onEditPage' => false,
        ]);
    }

    #[Route('/polls/{slug:poll}/authenticate', name: 'authenticate poll')]
    public function authenticate(Entity\Poll $poll, Request $request): Response
    {
        if (!$poll->isCompleted()) {
            throw $this->createNotFoundException('The poll doesn’t exist (yet).');
        }

        if (!$poll->isFullPasswordProtected() || $this->pollSecurity->isAuthenticated($poll)) {
            return $this->redirectToRoute('poll', [
                'slug' => $poll->getSlug(),
            ]);
        }

        $form = $this->createNamedForm('poll_authentication', Form\PollAuthenticationForm::class, options: [
            'poll' => $poll,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->pollSecurity->authenticate($poll);

            $this->addFlash('success', 'poll.authenticated');

            return $this->redirectToRoute('poll', [
                'slug' => $poll->getSlug(),
            ]);
        }

        return $this->render('polls/authenticate.html.twig', [
            'poll' => $poll,
            'form' => $form,
        ]);
    }

    #[Route('/polls/{id:poll}/{token}/edit', name: 'edit poll')]
    public function edit(Entity\Poll $poll, string $token, Request $request): Response
    {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        $flow = $this->pollFlowBuilder->build($poll);

        $form = $this->createNamedForm('poll', Form\PollForm::class, $poll);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $poll = $form->getData();

            $this->pollRepository->save($poll);

            return $this->redirect($flow->getNextStepUrl('init'));
        }

        return $this->render('polls/new.html.twig', [
            'poll' => $poll,
            'form' => $form,
            'flow' => $flow,
        ]);
    }

    #[Route('/polls/{id:poll}/{token}/settings', name: 'edit poll settings')]
    public function settings(Entity\Poll $poll, string $token, Request $request): Response
    {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        $flow = $this->pollFlowBuilder->build($poll);

        if (!$flow->isAccessible('summary')) {
            return $this->redirect($flow->getPreviousStepUrl('summary'));
        }

        $form = $this->createNamedForm('poll_settings', Form\PollSettingsForm::class, $poll);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $poll = $form->getData();

            $this->pollRepository->save($poll);

            if ($poll->isFullPasswordProtected()) {
                $this->pollSecurity->authenticate($poll);
            }

            return $this->redirect($flow->getStepUrl('summary'));
        }

        return $this->render('polls/settings.html.twig', [
            'poll' => $poll,
            'form' => $form,
            'flow' => $flow,
        ]);
    }

    #[Route('/polls/{id:poll}/{token}/proposals', name: 'edit poll proposals')]
    public function proposals(Entity\Poll $poll, string $token, Request $request): Response
    {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        if (!$poll->isClassicPoll()) {
            throw $this->createNotFoundException('The poll must be of type classic');
        }

        $flow = $this->pollFlowBuilder->build($poll);

        if (!$flow->isAccessible('proposals')) {
            return $this->redirect($flow->getPreviousStepUrl('proposals'));
        }

        $form = $this->createNamedForm('poll_proposals', Form\PollProposalsForm::class, $poll);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $poll = $form->getData();

            $this->pollRepository->save($poll);

            if ($request->request->get('step-action') === 'back') {
                return $this->redirect($flow->getPreviousStepUrl('proposals'));
            }

            return $this->redirect($flow->getNextStepUrl('proposals'));
        }

        return $this->render('polls/proposals.html.twig', [
            'poll' => $poll,
            'form' => $form,
            'flow' => $flow,
        ]);
    }

    #[Route('/polls/{id:poll}/{token}/dates', name: 'edit poll dates')]
    public function dates(Entity\Poll $poll, string $token, Request $request): Response
    {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        if (!$poll->isDatePoll()) {
            throw $this->createNotFoundException('The poll must be of type date');
        }

        $flow = $this->pollFlowBuilder->build($poll);

        if (!$flow->isAccessible('dates')) {
            return $this->redirect($flow->getPreviousStepUrl('dates'));
        }

        $form = $this->createNamedForm('poll_dates', Form\PollDatesForm::class, $poll);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $poll = $form->getData();

            $this->pollRepository->save($poll);

            if ($request->request->get('step-action') === 'back') {
                return $this->redirect($flow->getPreviousStepUrl('dates'));
            }

            return $this->redirect($flow->getNextStepUrl('dates'));
        }

        return $this->render('polls/dates.html.twig', [
            'poll' => $poll,
            'form' => $form,
            'flow' => $flow,
        ]);
    }

    #[Route('/polls/{id:poll}/{token}/slots', name: 'edit poll slots')]
    public function slots(Entity\Poll $poll, string $token, Request $request): Response
    {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        if (!$poll->isDatePoll()) {
            throw $this->createNotFoundException('The poll must be of type date');
        }

        $flow = $this->pollFlowBuilder->build($poll);

        if (!$flow->isAccessible('slots')) {
            return $this->redirect($flow->getPreviousStepUrl('slots'));
        }

        $form = $this->createNamedForm('poll_slots', Form\PollSlotsForm::class, $poll);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $poll = $form->getData();

            $this->pollRepository->save($poll);

            if ($request->request->get('step-action') === 'back') {
                return $this->redirect($flow->getPreviousStepUrl('slots'));
            }

            return $this->redirect($flow->getNextStepUrl('slots'));
        }

        return $this->render('polls/slots.html.twig', [
            'poll' => $poll,
            'form' => $form,
            'flow' => $flow,
        ]);
    }

    #[Route('/polls/{id:poll}/{token}/summary', name: 'poll summary')]
    public function summary(Entity\Poll $poll, string $token, Request $request): Response
    {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        if ($poll->isCompleted()) {
            return $this->redirectToRoute('poll admin', [
                'id' => $poll->getId(),
                'token' => $poll->getAdminToken(),
            ]);
        }

        $flow = $this->pollFlowBuilder->build($poll);

        if (!$flow->isAccessible('summary')) {
            return $this->redirect($flow->getPreviousStepUrl('summary'));
        }

        $form = $this->createNamedForm('poll_summary', Form\PollSummaryForm::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $poll->setCompletedAt(Utils\Time::now());

            $this->pollRepository->save($poll);

            $pollEvent = new PollActivity\PollEvent($poll);
            $this->eventDispatcher->dispatch($pollEvent, PollActivity\PollEvent::COMPLETED);

            $session = $request->getSession();
            $session->set("admin-{$poll->getId()}", true);

            if ($request->request->get('step-action') === 'back') {
                return $this->redirect($flow->getPreviousStepUrl('summary'));
            }

            return $this->redirect($flow->getNextStepUrl('summary'));
        }

        return $this->render('polls/summary.html.twig', [
            'poll' => $poll,
            'form' => $form,
            'flow' => $flow,
        ]);
    }

    #[Route('/polls/{id:poll}/{token}/complete', name: 'poll complete')]
    public function complete(Entity\Poll $poll, string $token): Response
    {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        $flow = $this->pollFlowBuilder->build($poll);

        if (!$flow->isAccessible('end')) {
            return $this->redirect($flow->getPreviousStepUrl('end'));
        }

        return $this->render('polls/complete.html.twig', [
            'poll' => $poll,
            'flow' => $flow,
        ]);
    }

    #[Route('/polls/{id:poll}/{token}/admin', name: 'poll admin')]
    public function admin(Entity\Poll $poll, string $token, Request $request): Response
    {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        if (!$poll->isCompleted()) {
            return $this->redirectToRoute('poll summary', [
                'id' => $poll->getId(),
                'token' => $poll->getAdminToken(),
                'flow' => 'on',
            ]);
        }

        $session = $request->getSession();
        $session->set("admin-{$poll->getId()}", true);

        return $this->render('polls/admin.html.twig', [
            'poll' => $poll,
        ]);
    }

    #[Route('/polls/{id:poll}/{token}/deletion', name: 'delete poll')]
    public function deletion(Entity\Poll $poll, string $token, Request $request): Response
    {
        if ($poll->getAdminToken() !== $token) {
            throw $this->createNotFoundException('The admin token doesn’t match.');
        }

        $form = $this->createNamedForm('poll_deletion', Form\PollDeletionForm::class, $poll);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->pollRepository->remove($poll, true);

            $this->addFlash('success', 'poll.deleted');

            return $this->redirectToRoute('home');
        }

        return $this->render('polls/deletion.html.twig', [
            'poll' => $poll,
            'form' => $form,
        ]);
    }
}
