<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry;

class PublicPollsControllerTest extends WebTestCase
{
    use Foundry\Test\Factories;
    use Foundry\Test\ResetDatabase;

    public function testGetPublicPollsShowsOnlyEligiblePublicPolls(): void
    {
        $client = static::createClient();

        Factory\PollFactory::new([
            'title' => 'Listed poll',
            'authorName' => 'Alice',
            'areResultsPublic' => true,
            'isPubliclyListed' => true,
        ])->completed()->create();

        Factory\PollFactory::new([
            'title' => 'Unlisted poll',
            'authorName' => 'Bob',
            'areResultsPublic' => true,
            'isPubliclyListed' => false,
        ])->completed()->create();

        Factory\PollFactory::new([
            'title' => 'Private results poll',
            'authorName' => 'Carol',
            'areResultsPublic' => false,
            'isPubliclyListed' => true,
        ])->completed()->create();

        Factory\PollFactory::new([
            'title' => 'Protected poll',
            'authorName' => 'Dave',
            'areResultsPublic' => true,
            'isPubliclyListed' => true,
            'password' => 'secret',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();

        Factory\PollFactory::new([
            'title' => 'Closed poll',
            'authorName' => 'Eve',
            'areResultsPublic' => true,
            'isPubliclyListed' => true,
            'closedAt' => Utils\Time::fromNow(-1, 'day'),
        ])->completed()->create();

        $client->request(Request::METHOD_GET, '/polls/public');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Public polls');
        $this->assertSelectorTextContains('main', 'Listed poll');
        $this->assertSelectorTextNotContains('main', 'Unlisted poll');
        $this->assertSelectorTextNotContains('main', 'Private results poll');
        $this->assertSelectorTextNotContains('main', 'Protected poll');
        $this->assertSelectorTextNotContains('main', 'Closed poll');
    }
}
