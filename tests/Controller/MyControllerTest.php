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

use App\Tests\Helper;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry;

class MyControllerTest extends WebTestCase
{
    use Foundry\Test\Factories;
    use Foundry\Test\ResetDatabase;
    use Helper\CsrfHelper;
    use Helper\FactoryHelper;

    public function testGetMyRendersCorrectly(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/my');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My polls and votes');
    }

    public function testGetMyRendersCorrectlyASuccessfulMessage(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/my', [
            'mailSent' => true,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            'p[role="alert"]',
            'We have sent you an email containing links to your polls.'
        );
    }

    public function testPostMySendsAnEmail(): void
    {
        $client = static::createClient();
        $authorEmail = 'alix@example.org';
        $poll = Factory\PollFactory::new([
            'authorEmail' => $authorEmail,
        ])->completed()->create();

        $client->request(Request::METHOD_POST, '/my', [
            'search_polls' => [
                '_token' => $this->getCsrf($client, 'search_polls'),
                'email' => $authorEmail,
            ],
        ]);

        $this->assertResponseRedirects('/my?mailSent=1', 302);
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertNotNull($email);
        $this->assertEmailTextBodyContains($email, $poll->getTitle() ?? '');
        $this->assertEmailAddressContains($email, 'To', $authorEmail);
    }

    public function testPostMyDoesNotSendEmailIfNoPolls(): void
    {
        $client = static::createClient();
        $authorEmail = 'alix@example.org';

        $client->request(Request::METHOD_POST, '/my', [
            'search_polls' => [
                '_token' => $this->getCsrf($client, 'search_polls'),
                'email' => $authorEmail,
            ],
        ]);

        $this->assertResponseRedirects('/my?mailSent=1', 302);
        $this->assertEmailCount(0);
    }

    public function testPostMyFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $authorEmail = 'alix@example.org';
        $poll = Factory\PollFactory::new([
            'authorEmail' => $authorEmail,
        ])->completed()->create();

        $client->request(Request::METHOD_POST, '/my', [
            'search_polls' => [
                '_token' => 'not the token',
                'email' => $authorEmail,
            ],
        ]);

        $this->assertSelectorTextContains('#search_polls_error', 'please submit the form again');
        $this->assertEmailCount(0);
    }
}
