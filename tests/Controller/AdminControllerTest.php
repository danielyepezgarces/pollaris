<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AdminControllerTest extends WebTestCase
{
    public function testGetShowRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'roles' => ['ROLE_ADMIN'],
        ]);
        $client->loginUser($user);

        $client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Administration');
    }

    public function testGetShowRedirectsIfNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseRedirects('/login', 302);
    }

    public function testGetShowFailsIfUserIsNotAdmin(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'roles' => ['ROLE_USER'],
        ]);
        $client->loginUser($user);

        $this->expectException(AccessDeniedException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/admin');
    }
}
