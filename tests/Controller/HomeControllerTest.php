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

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class HomeControllerTest extends WebTestCase
{
    public function testGetShowRendersCorrectly(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Coordinate events and make decisions with your Wikimedia community.');
        $this->assertSelectorExists('meta[name="description"][content*="Use this polling tool to organise edit-a-thons"]');
        $this->assertSelectorExists('meta[property="og:title"][content="Welcome to Pollaris!"]');
        $this->assertSelectorExists('meta[property="og:image"][content="http://localhost/screenshot.webp"]');
        $this->assertSelectorExists('meta[property="og:image:type"][content="image/webp"]');
        $this->assertSelectorExists('meta[property="og:image:width"][content="1280"]');
        $this->assertSelectorExists('meta[property="og:image:height"][content="800"]');
        $this->assertSelectorExists('meta[name="twitter:card"][content="summary_large_image"]');
        $this->assertSelectorExists('meta[name="robots"][content="index, follow"]');
    }
}
