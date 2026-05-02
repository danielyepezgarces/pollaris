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

namespace App\Tests\Service;

use App\Service\WikimediaOAuth;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WikimediaOAuthTest extends TestCase
{
    public function testBuildAuthorizationUrlSendsOutOfBandCallback(): void
    {
        $capturedMethod = null;
        $capturedOptions = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedOptions): MockResponse {
            $capturedMethod = $method;
            $capturedOptions = $options;

            return new MockResponse('{"key":"request-token","secret":"request-secret"}');
        });

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://pollaris.example/login/wikimedia/callback');

        $wikimediaOAuth = new WikimediaOAuth(
            $httpClient,
            $urlGenerator,
            true,
            'https://meta.wikimedia.org/w/index.php',
            'client-id',
            'client-secret',
            'Pollaris',
            'https://pollaris.example',
            '/home/dyepezgdev/Development/Pollaris',
        );

        $session = new Session(new MockArraySessionStorage());

        $authorizationUrl = $wikimediaOAuth->buildAuthorizationUrl($session);

        self::assertStringContainsString('oauth_token=request-token', $authorizationUrl);
        self::assertSame('request-token', $session->get(WikimediaOAuth::SESSION_REQUEST_TOKEN_KEY));
        self::assertSame('request-secret', $session->get(WikimediaOAuth::SESSION_REQUEST_TOKEN_SECRET));
        self::assertSame('GET', $capturedMethod);
        self::assertNotNull($capturedOptions);
        self::assertSame([
            'title' => 'Special:OAuth/initiate',
            'format' => 'json',
        ], $capturedOptions['query']);
        self::assertArrayHasKey('normalized_headers', $capturedOptions);
        self::assertArrayHasKey('authorization', $capturedOptions['normalized_headers']);
        self::assertStringContainsString('oauth_callback="oob"', $capturedOptions['normalized_headers']['authorization'][0]);
    }

    public function testHasValidCallbackAcceptsMissingOauthToken(): void
    {
        $wikimediaOAuth = new WikimediaOAuth(
            new MockHttpClient(),
            $this->createMock(UrlGeneratorInterface::class),
            true,
            'https://meta.wikimedia.org/w/index.php',
            'client-id',
            'client-secret',
            'Pollaris',
            'https://pollaris.example',
            '/home/dyepezgdev/Development/Pollaris',
        );

        $session = new Session(new MockArraySessionStorage());
        $session->set(WikimediaOAuth::SESSION_REQUEST_TOKEN_KEY, 'request-token');
        $session->set(WikimediaOAuth::SESSION_REQUEST_TOKEN_SECRET, 'request-secret');

        self::assertTrue($wikimediaOAuth->hasValidCallback($session, '', 'verifier'));
        self::assertTrue($wikimediaOAuth->hasValidCallback($session, 'request-token', 'verifier'));
        self::assertFalse($wikimediaOAuth->hasValidCallback($session, 'other-token', 'verifier'));
        self::assertFalse($wikimediaOAuth->hasValidCallback($session, '', ''));
    }
}
