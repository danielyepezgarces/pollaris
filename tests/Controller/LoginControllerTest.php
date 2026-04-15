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

namespace App\Tests\Controller;

use App\Entity;
use App\Repository;
use App\Service;
use App\Tests\Helper;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LoginControllerTest extends WebTestCase
{
    use Factories;
    use Helper\CsrfHelper;
    use ResetDatabase;

    public function testGetLoginRendersCorrectly(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/login');

        $this->assertResponseIsSuccessful();
        $user = $this->getLoggedUser();
        $this->assertNull($user);
    }

    public function testGetLoginRedirectsIfAlreadyConnected(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user);

        $client->request(Request::METHOD_GET, '/login');

        $this->assertResponseRedirects('/admin', 302);
        $user = $this->getLoggedUser();
        $this->assertNotNull($user);
    }

    public function testPostLoginLogsTheUserAndRedirectsToHome(): void
    {
        $client = static::createClient();
        $username = 'admin';
        $password = 'secret';
        $user = Factory\UserFactory::createOne([
            'username' => $username,
            'password' => $password,
        ]);

        $client->request(Request::METHOD_POST, '/login', [
            '_csrf_token' => $this->getCsrf($client, 'authenticate'),
            '_username' => $username,
            '_password' => $password,
        ]);

        $this->assertResponseRedirects('/admin', 302);
        $user = $this->getLoggedUser();
        $this->assertNotNull($user);
    }

    public function testPostLoginFailsIfPasswordIsIncorrect(): void
    {
        $client = static::createClient();
        $username = 'admin';
        $password = 'secret';
        $user = Factory\UserFactory::createOne([
            'username' => $username,
            'password' => $password,
        ]);

        $client->request(Request::METHOD_POST, '/login', [
            '_csrf_token' => $this->getCsrf($client, 'authenticate'),
            '_username' => $username,
            '_password' => 'not the password',
        ]);

        $this->assertResponseRedirects('/login', 302);
        $client->followRedirect();

        $this->assertSelectorTextContains(
            '#login-error',
            'Invalid credentials.'
        );
        $user = $this->getLoggedUser();
        $this->assertNull($user);
    }

    public function testPostLoginFailsIfUserDoesNotExist(): void
    {
        $client = static::createClient();
        $username = 'admin';
        $password = 'secret';

        $client->request(Request::METHOD_POST, '/login', [
            '_csrf_token' => $this->getCsrf($client, 'authenticate'),
            '_username' => $username,
            '_password' => $password
        ]);

        $this->assertResponseRedirects('/login', 302);
        $client->followRedirect();

        $this->assertSelectorTextContains(
            '#login-error',
            'Invalid credentials.'
        );
        $user = $this->getLoggedUser();
        $this->assertNull($user);
    }

    public function testPostLogoutLogsUserOutAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user);

        $client->request(Request::METHOD_POST, '/logout', [
            '_csrf_token' => $this->getCsrf($client, 'authenticate'),
        ]);

        $this->assertResponseRedirects('http://localhost/', 302);
        $user = $this->getLoggedUser();
        $this->assertNull($user);
    }

    public function testWikimediaLoginDoesNotRenameExistingUser(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'existing-name',
            'wikimediaId' => 'wikimedia-123',
        ]);

        $wikimediaOAuth = $this->createMock(Service\WikimediaOAuth::class);
        $wikimediaOAuth->method('isConfigured')->willReturn(true);
        $wikimediaOAuth->method('hasValidCallback')->willReturn(true);
        $wikimediaOAuth->method('fetchProfile')->willReturn([
            'sub' => 'wikimedia-123',
            'username' => 'new-wikimedia-name',
            'realname' => '',
            'email' => '',
        ]);
        static::getContainer()->set(Service\WikimediaOAuth::class, $wikimediaOAuth);

        $client->request(Request::METHOD_GET, '/login/wikimedia/callback', [
            'oauth_token' => 'token',
            'oauth_verifier' => 'verifier',
        ]);

        $this->assertResponseRedirects('/admin', 302);
        /** @var Repository\UserRepository $userRepository */
        $userRepository = static::getContainer()->get(Repository\UserRepository::class);
        $reloadedUser = $userRepository->find($user->getId());
        $this->assertNotNull($reloadedUser);
        $this->assertSame('existing-name', $reloadedUser->getUsername());
    }

    protected function getLoggedUser(): ?Entity\User
    {
        /** @var TokenStorageInterface */
        $tokenStorage = $this->getContainer()->get(TokenStorageInterface::class);
        $token = $tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        /** @var ?Entity\User */
        $user = $token->getUser();
        return $user;
    }
}
