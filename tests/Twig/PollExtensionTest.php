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

namespace App\Tests\Twig;

use App\Twig\PollExtension;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PollExtensionTest extends WebTestCase
{
    public function testFlatten(): void
    {
        $container = static::getContainer();
        /** @var PollExtension */
        $pollExtension = $container->get(PollExtension::class);
        $arrays = [
            ['foo', 'bar', 'baz'],
            ['spam', 'ham', 'eggs'],
        ];

        $result = $pollExtension->flatten($arrays);

        $this->assertSame(['foo', 'bar', 'baz', 'spam', 'ham', 'eggs'], $result);
    }

    public function testCalendarTwigFunctions(): void
    {
        $container = static::getContainer();
        /** @var PollExtension */
        $pollExtension = $container->get(PollExtension::class);

        $poll = new \App\Entity\Poll();
        $poll->setTitle('Test Meeting');
        $poll->setSlug('test-meeting');
        $poll->setTimezoneName('UTC');

        $date = new \App\Entity\Date();
        $date->setValue(new \DateTimeImmutable('2026-11-20 00:00:00', new \DateTimeZone('UTC')));

        $proposal = new \App\Entity\Proposal();
        $proposal->setDate($date);
        $proposal->setStartTime('10:00');

        $gcal = $pollExtension->googleCalendarUrl($poll, $proposal);
        $this->assertStringContainsString('calendar.google.com', $gcal);

        $outlook = $pollExtension->outlookCalendarUrl($poll, $proposal);
        $this->assertStringContainsString('outlook.live.com', $outlook);

        $yahoo = $pollExtension->yahooCalendarUrl($poll, $proposal);
        $this->assertStringContainsString('calendar.yahoo.com', $yahoo);

        $webcal = $pollExtension->webcalUrl($poll, $proposal);
        $this->assertStringStartsWith('webcal://', $webcal);
    }
}
