<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Repository;
use App\Service;
use App\Utils;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LoginController extends BaseController
{
    public function __construct(
        private readonly Security $security,
        private readonly Service\WikimediaOAuth $wikimediaOAuth,
        private readonly Repository\UserRepository $userRepository,
    ) {
    }

    #[Route(path: '/login', name: 'login')]
    public function login(Request $request): Response
    {
        $user = $this->getUser();
        if ($user) {
            return $this->redirectToRoute('admin');
        }

        $returnto = $request->query->getString('returnto');
        if ($returnto !== '' && str_starts_with($returnto, '/') && !str_starts_with($returnto, '//')) {
            $request->getSession()->set('login_returnto', $returnto);
        }

        if ($this->wikimediaOAuth->isConfigured()) {
            return $this->redirectToRoute('login wikimedia');
        }

        return $this->render('login/login.html.twig', [
            'wikimediaOAuthEnabled' => false,
        ]);
    }

    #[Route(path: '/login/wikimedia', name: 'login wikimedia')]
    public function loginWithWikimedia(Request $request): Response
    {
        if (!$this->wikimediaOAuth->isConfigured()) {
            throw $this->createNotFoundException('Wikimedia OAuth is not configured.');
        }

        return $this->redirect($this->wikimediaOAuth->buildAuthorizationUrl($request->getSession()));
    }

    #[Route(path: '/login/wikimedia/callback', name: 'login wikimedia callback')]
    public function loginWithWikimediaCallback(Request $request): Response
    {
        if (!$this->wikimediaOAuth->isConfigured()) {
            throw $this->createNotFoundException('Wikimedia OAuth is not configured.');
        }

        $oauthToken = $request->query->getString('oauth_token');
        $oauthVerifier = $request->query->getString('oauth_verifier');

        if (!$this->wikimediaOAuth->hasValidCallback($request->getSession(), $oauthToken, $oauthVerifier)) {
            $this->addFlash('error', 'login.wikimedia.error.cancelled');

            return $this->redirectToRoute('login');
        }

        try {
            $profile = $this->wikimediaOAuth->fetchProfile($request->getSession(), $oauthVerifier);
            $user = $this->synchronizeWikimediaUser($profile);
        } catch (\Throwable) {
            $this->addFlash('error', 'login.wikimedia.error.generic');

            return $this->redirectToRoute('login');
        }

        $response = $this->security->login($user, 'form_login', 'main');

        $returnto = $request->getSession()->get('login_returnto');
        if (is_string($returnto) && str_starts_with($returnto, '/') && !str_starts_with($returnto, '//')) {
            $request->getSession()->remove('login_returnto');

            return $this->redirect($returnto);
        }

        return $response ?? $this->redirectToRoute('admin');
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): void
    {
        // controller can be blank: it will never be called!
        throw new \Exception('Don’t forget to activate logout in security.yaml');
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function synchronizeWikimediaUser(array $profile): Entity\User
    {
        $wikimediaId = (string) ($profile['sub'] ?? $profile['username'] ?? '');
        $wikimediaUsername = trim((string) ($profile['username'] ?? ''));

        if ($wikimediaId === '' || $wikimediaUsername === '') {
            throw new \RuntimeException('Wikimedia profile is incomplete.');
        }

        $user = $this->userRepository->findOneByWikimediaId($wikimediaId);

        if (!$user) {
            $user = new Entity\User();
            $user->setWikimediaId($wikimediaId);
            $user->setUsername($this->buildAvailableUsername($wikimediaUsername));
            $user->setRoles(['ROLE_USER']);
            $user->setPassword(Utils\Random::hex(64));
        }

        $realName = trim((string) ($profile['realname'] ?? ''));
        $email = trim((string) ($profile['email'] ?? ''));

        $user->setRealName($realName !== '' ? $realName : null);
        $user->setEmail($email !== '' ? $email : null);

        $this->userRepository->save($user);

        return $user;
    }

    private function buildAvailableUsername(string $preferredUsername): string
    {
        $baseUsername = mb_substr($preferredUsername, 0, Entity\User::MAX_USERNAME_LENGTH);

        if ($this->userRepository->findOneByUsername($baseUsername) === null) {
            return $baseUsername;
        }

        $suffix = 1;

        do {
            $suffixText = "-{$suffix}";
            $candidate = mb_substr(
                $baseUsername,
                0,
                Entity\User::MAX_USERNAME_LENGTH - mb_strlen($suffixText),
            ) . $suffixText;
            ++$suffix;
        } while ($this->userRepository->findOneByUsername($candidate) !== null);

        return $candidate;
    }
}
