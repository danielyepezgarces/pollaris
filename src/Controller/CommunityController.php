<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommunityController extends BaseController
{
    public function __construct(
        private Repository\CommunityRepository $communityRepository,
        private Repository\CommunityMemberRepository $memberRepository,
        private Repository\UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/communities', name: 'community_index')]
    public function index(): Response
    {
        $currentUser = $this->getUser();
        $myCommunities = [];
        if ($currentUser instanceof Entity\User) {
            $myCommunities = $this->communityRepository->findForUser($currentUser);
        }

        $allCommunities = $this->communityRepository->findBy([], ['name' => 'ASC']);

        return $this->render('community/index.html.twig', [
            'myCommunities' => $myCommunities,
            'allCommunities' => $allCommunities,
        ]);
    }

    #[Route('/communities/new', name: 'community_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var Entity\User $currentUser */
        $currentUser = $this->getUser();

        $community = new Entity\Community();
        $community->setOwner($currentUser);

        $form = $this->createForm(Form\CommunityForm::class, $community);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Also add owner as active admin member
            $member = new Entity\CommunityMember();
            $member->setCommunity($community);
            $member->setUser($currentUser);
            $member->setRole(Entity\CommunityMember::ROLE_ADMIN);
            $member->setStatus(Entity\CommunityMember::STATUS_ACTIVE);
            $community->addMember($member);

            $this->entityManager->persist($community);
            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('community.flash.created'));
            return $this->redirectToRoute('community_show', ['slug' => $community->getSlug()]);
        }

        return $this->render('community/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/communities/{slug}', name: 'community_show', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $community = $this->communityRepository->findOneBySlug($slug);
        if (!$community) {
            throw $this->createNotFoundException();
        }

        $currentUser = $this->getUser();
        $currentMembership = null;
        $isAdmin = false;

        if ($currentUser instanceof Entity\User) {
            $currentMembership = $this->memberRepository->findMembership($community, $currentUser);
            $isAdmin = $community->isAdmin($currentUser);
        }

        $activeMembers = $this->memberRepository->findActiveMembers($community);
        $pendingMembers = $isAdmin ? $this->memberRepository->findPendingMembers($community) : [];

        $addMemberForm = null;
        if ($isAdmin) {
            $addMemberForm = $this->createForm(Form\CommunityAddMemberForm::class);
        }

        return $this->render('community/show.html.twig', [
            'community' => $community,
            'currentMembership' => $currentMembership,
            'isAdmin' => $isAdmin,
            'activeMembers' => $activeMembers,
            'pendingMembers' => $pendingMembers,
            'addMemberForm' => $addMemberForm,
            'inviteToken' => $request->query->get('token', $community->getInviteToken()),
        ]);
    }

    #[Route('/communities/{slug}/join', name: 'community_join', methods: ['POST'])]
    public function join(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var Entity\User $currentUser */
        $currentUser = $this->getUser();

        $community = $this->communityRepository->findOneBySlug($slug);
        if (!$community) {
            throw $this->createNotFoundException();
        }

        $membership = $this->memberRepository->findMembership($community, $currentUser);
        if ($membership && $membership->getStatus() === Entity\CommunityMember::STATUS_ACTIVE) {
            $this->addFlash('info', $this->translator->trans('community.flash.already_member'));
            return $this->redirectToRoute('community_show', ['slug' => $slug]);
        }

        $token = $request->request->get('token');
        if ($token && !hash_equals($community->getInviteToken() ?? '', $token)) {
            $this->addFlash('error', $this->translator->trans('community.flash.invalid_token'));
            return $this->redirectToRoute('community_show', ['slug' => $slug]);
        }

        if (!$membership) {
            $membership = new Entity\CommunityMember();
            $membership->setCommunity($community);
            $membership->setUser($currentUser);
            $membership->setRole(Entity\CommunityMember::ROLE_MEMBER);
        }

        if ($community->getJoinPolicy() === Entity\Community::JOIN_POLICY_OPEN) {
            $membership->setStatus(Entity\CommunityMember::STATUS_ACTIVE);
            $this->entityManager->persist($membership);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('community.flash.joined_success'));
        } else {
            $membership->setStatus(Entity\CommunityMember::STATUS_PENDING);
            $this->entityManager->persist($membership);
            $this->entityManager->flush();
            $this->addFlash('info', $this->translator->trans('community.flash.join_pending'));
        }

        return $this->redirectToRoute('community_show', ['slug' => $slug]);
    }

    #[Route('/communities/{slug}/members/add', name: 'community_add_member', methods: ['POST'])]
    public function addMember(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var Entity\User $currentUser */
        $currentUser = $this->getUser();

        $community = $this->communityRepository->findOneBySlug($slug);
        if (!$community || !$community->isAdmin($currentUser)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(Form\CommunityAddMemberForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $username = trim($data['username']);
            $targetUser = $this->userRepository->findOneByUsername($username);

            if (!$targetUser) {
                $this->addFlash('error', $this->translator->trans('community.flash.user_not_found', ['username' => $username]));
                return $this->redirectToRoute('community_show', ['slug' => $slug]);
            }

            $membership = $this->memberRepository->findMembership($community, $targetUser);
            if (!$membership) {
                $membership = new Entity\CommunityMember();
                $membership->setCommunity($community);
                $membership->setUser($targetUser);
            }

            $membership->setRole($data['role']);
            $membership->setStatus(Entity\CommunityMember::STATUS_ACTIVE);

            $this->entityManager->persist($membership);
            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('community.flash.member_added', ['username' => $username]));
        }

        return $this->redirectToRoute('community_show', ['slug' => $slug]);
    }

    #[Route('/communities/{slug}/members/{id}/approve', name: 'community_approve_member', methods: ['POST'])]
    public function approveMember(string $slug, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var Entity\User $currentUser */
        $currentUser = $this->getUser();

        $community = $this->communityRepository->findOneBySlug($slug);
        if (!$community || !$community->isAdmin($currentUser)) {
            throw $this->createAccessDeniedException();
        }

        $member = $this->memberRepository->find($id);
        if ($member && $member->getCommunity() === $community) {
            $member->setStatus(Entity\CommunityMember::STATUS_ACTIVE);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('community.flash.member_approved'));
        }

        return $this->redirectToRoute('community_show', ['slug' => $slug]);
    }

    #[Route('/communities/{slug}/members/{id}/remove', name: 'community_remove_member', methods: ['POST'])]
    public function removeMember(string $slug, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var Entity\User $currentUser */
        $currentUser = $this->getUser();

        $community = $this->communityRepository->findOneBySlug($slug);
        if (!$community || !$community->isAdmin($currentUser)) {
            throw $this->createAccessDeniedException();
        }

        $member = $this->memberRepository->find($id);
        if ($member && $member->getCommunity() === $community) {
            if ($member->getUser() === $community->getOwner()) {
                $this->addFlash('error', $this->translator->trans('community.flash.cannot_remove_owner'));
            } else {
                $this->entityManager->remove($member);
                $this->entityManager->flush();
                $this->addFlash('success', $this->translator->trans('community.flash.member_removed'));
            }
        }

        return $this->redirectToRoute('community_show', ['slug' => $slug]);
    }

    #[Route('/communities/{slug}/leave', name: 'community_leave', methods: ['POST'])]
    public function leave(string $slug): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var Entity\User $currentUser */
        $currentUser = $this->getUser();

        $community = $this->communityRepository->findOneBySlug($slug);
        if (!$community) {
            throw $this->createNotFoundException();
        }

        if ($community->getOwner() === $currentUser) {
            $this->addFlash('error', $this->translator->trans('community.flash.owner_cannot_leave'));
            return $this->redirectToRoute('community_show', ['slug' => $slug]);
        }

        $membership = $this->memberRepository->findMembership($community, $currentUser);
        if ($membership) {
            $this->entityManager->remove($membership);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('community.flash.left_success'));
        }

        return $this->redirectToRoute('community_index');
    }

    #[Route('/communities/{slug}/settings', name: 'community_settings', methods: ['GET', 'POST'])]
    public function settings(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var Entity\User $currentUser */
        $currentUser = $this->getUser();

        $community = $this->communityRepository->findOneBySlug($slug);
        if (!$community || !$community->isAdmin($currentUser)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(Form\CommunityForm::class, $community);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('community.flash.settings_updated'));
            return $this->redirectToRoute('community_show', ['slug' => $community->getSlug()]);
        }

        return $this->render('community/edit.html.twig', [
            'community' => $community,
            'form' => $form,
        ]);
    }

    #[Route('/communities/{slug}/regenerate-token', name: 'community_regenerate_token', methods: ['POST'])]
    public function regenerateToken(string $slug): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var Entity\User $currentUser */
        $currentUser = $this->getUser();

        $community = $this->communityRepository->findOneBySlug($slug);
        if (!$community || !$community->isAdmin($currentUser)) {
            throw $this->createAccessDeniedException();
        }

        $community->regenerateInviteToken();
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('community.flash.token_regenerated'));
        return $this->redirectToRoute('community_show', ['slug' => $slug]);
    }
}
