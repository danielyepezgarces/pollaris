<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

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
