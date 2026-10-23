<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Repository;
use App\Service\CommunityNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommunityOptOutController extends BaseController
{
    public function __construct(
        private Repository\PollRepository $pollRepository,
        private Repository\CommunityRepository $communityRepository,
        private Repository\CommunityMemberRepository $memberRepository,
        private Repository\UserRepository $userRepository,
        private Repository\PollNotificationOptOutRepository $optOutRepository,
        private CommunityNotificationService $notificationService,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/notifications/opt-out/poll/{pollSlug}/{userId}/{token}', name: 'community_opt_out_poll', methods: ['GET'])]
    public function optOutPoll(string $pollSlug, int $userId, string $token): Response
    {
        $poll = $this->pollRepository->loadBySlug($pollSlug);
        $user = $this->userRepository->find($userId);

        if (!$poll || !$user || !$this->notificationService->verifyOptOutToken($poll, $user, $token)) {
            throw $this->createNotFoundException('Invalid opt-out link.');
        }

        if (!$this->optOutRepository->isOptedOut($poll, $user)) {
            $optOut = new Entity\PollNotificationOptOut();
            $optOut->setPoll($poll);
            $optOut->setUser($user);
            $optOut->setEmail($user->getEmail());
            $this->entityManager->persist($optOut);
            $this->entityManager->flush();
        }

        return $this->render('community/opt_out_success.html.twig', [
            'type' => 'poll',
            'poll' => $poll,
            'user' => $user,
        ]);
    }

    #[Route('/notifications/opt-out/community/{communitySlug}/{userId}/{token}', name: 'community_opt_out_community', methods: ['GET'])]
    public function optOutCommunity(string $communitySlug, int $userId, string $token): Response
    {
        $community = $this->communityRepository->findOneBySlug($communitySlug);
        $user = $this->userRepository->find($userId);

        if (!$community || !$user || !$this->notificationService->verifyCommunityOptOutToken($community, $user, $token)) {
            throw $this->createNotFoundException('Invalid opt-out link.');
        }

        $membership = $this->memberRepository->findMembership($community, $user);
        if ($membership) {
            $membership->setReceiveEmails(false);
            $this->entityManager->flush();
        }

        return $this->render('community/opt_out_success.html.twig', [
            'type' => 'community',
            'community' => $community,
            'user' => $user,
        ]);
    }
}
