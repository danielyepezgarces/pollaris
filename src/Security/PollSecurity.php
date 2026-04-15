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

namespace App\Security;

use App\Entity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PollSecurity
{
    public function __construct(
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
        #[Autowire('%kernel.secret%')]
        #[\SensitiveParameter]
        private string $secret,
    ) {
    }

    public function hasAccessToAdmin(Entity\Poll $poll): bool
    {
        $session = $this->requestStack->getSession();
        return $session->get("admin-{$poll->getId()}") === true;
    }

    public function canViewResults(Entity\Poll $poll): bool
    {
        return $poll->areResultsPublic() || $this->hasAccessToAdmin($poll);
    }

    public function canEditVotes(Entity\Poll $poll, bool $ignoreAdminAccess = false): bool
    {
        $hasAdminAccess = !$ignoreAdminAccess && $this->hasAccessToAdmin($poll);

        return (
            !$poll->isClosed() &&
            (
                $poll->getEditVoteMode() !== 'no' ||
                $hasAdminAccess
            )
        );
    }

    public function canEditVote(Entity\Vote $vote, bool $ignoreAdminAccess = false): bool
    {
        $poll = $vote->getPoll();

        $hasAdminAccess = !$ignoreAdminAccess && $this->hasAccessToAdmin($poll);

        // For logged-in users, check ownership via DB
        $currentUser = $this->tokenStorage->getToken()?->getUser();
        $isOwner = $currentUser instanceof Entity\User && $vote->getOwner()?->getId() === $currentUser->getId();

        // For guests, fall back to session-based check
        $session = $this->requestStack->getSession();
        $myVoteId = $session->get("vote-{$poll->getId()}");
        $myVoteIsGivenOne = !$isOwner && $myVoteId === $vote->getId();

        return (
            !$poll->isClosed() && (
                $poll->getEditVoteMode() === 'any' ||
                $isOwner ||
                $myVoteIsGivenOne ||
                $hasAdminAccess
            )
        );
    }

    public function isAuthenticated(Entity\Poll $poll): bool
    {
        if (!$poll->isFullPasswordProtected()) {
            return true;
        }

        $session = $this->requestStack->getSession();

        $key = $this->generateKey($poll);
        $sessionHash = $session->get($key);

        if (!is_string($sessionHash)) {
            return false;
        }

        return $this->compareHash($poll, $sessionHash);
    }

    public function authenticate(Entity\Poll $poll): void
    {
        $key = $this->generateKey($poll);
        $hash = $this->generateHash($poll);

        $session = $this->requestStack->getSession();
        $session->set($key, $hash);
    }

    public function generateKey(Entity\Poll $poll): string
    {
        return "poll-{$poll->getId()}-auth";
    }

    public function generateHash(Entity\Poll $poll): string
    {
        $data = "{$poll->getId()}:{$poll->getPassword()}";
        return hash_hmac('sha256', $data, $this->secret);
    }

    public function compareHash(Entity\Poll $poll, string $sessionHash): bool
    {
        $expectedHash = $this->generateHash($poll);
        return hash_equals($expectedHash, $sessionHash);
    }
}
