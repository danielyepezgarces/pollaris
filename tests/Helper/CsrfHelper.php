<?php

// This file is part of Pollaris.
// Copyright 2022-2024 Probesys (Bileto)
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

namespace App\Tests\Helper;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

trait CsrfHelper
{
    public function getSession(KernelBrowser $client): SessionInterface
    {
        $cookie = $client->getCookieJar()->get('MOCKSESSID');
        if ($cookie) {
            /** @var SessionFactory */
            $sessionFactory = static::getContainer()->get('session.factory');
            $session = $sessionFactory->createSession();
            $session->setId($cookie->getValue());
            $session->start();
            return $session;
        } else {
            return self::createSession($client);
        }
    }

    public function createSession(KernelBrowser $client): SessionInterface
    {
        /** @var SessionFactory */
        $sessionFactory = static::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();
        $session->start();
        $session->save();

        $sessionCookie = new Cookie(
            $session->getName(),
            $session->getId(),
            null,
            null,
            'localhost',
        );
        $client->getCookieJar()->set($sessionCookie);

        return $session;
    }

    public function getCsrf(KernelBrowser $client, string $tokenId): string
    {
        $session = $this->getSession($client);
        $container = static::getContainer();
        /** @var TokenGeneratorInterface $tokenGenerator */
        $tokenGenerator = $container->get('security.csrf.token_generator');
        $csrfToken = $tokenGenerator->generateToken();
        $session->set(SessionTokenStorage::SESSION_NAMESPACE . "/{$tokenId}", $csrfToken);
        $session->save();
        return $csrfToken;
    }
}
