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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class PreferencesControllerTest extends WebTestCase
{
    use Helper\CsrfHelper;

    public function testPostEditSavesTheLocaleInTheSessionAndRedirects(): void
    {
        $client = static::createClient();
        $session = $this->getSession($client);

        $client->request(Request::METHOD_POST, '/preferences', [
            'preferences' => [
                '_token' => $this->getCsrf($client, 'preferences'),
                'locale' => 'fr_FR',
            ],
        ]);

        $this->assertResponseRedirects('/', 302);
        $this->assertSame('fr_FR', $session->get('_locale'));
    }

    public function testPostUpdateLocaleFailsIfLocaleIsInvalid(): void
    {
        $client = static::createClient();
        $session = $this->getSession($client);

        $client->request(Request::METHOD_POST, '/preferences', [
            'preferences' => [
                '_token' => $this->getCsrf($client, 'preferences'),
                'locale' => 'not a locale',
            ],
        ]);

        $this->assertSelectorTextContains('#preferences_locale_error', 'The selected choice is invalid');
        $this->assertNull($session->get('_locale'));
    }
}
