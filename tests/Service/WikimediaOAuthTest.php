<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

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
        $capturedOptions = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;

            return new MockResponse('oauth_token=request-token&oauth_token_secret=request-secret');
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
        self::assertNotNull($capturedOptions);
        self::assertArrayHasKey('normalized_headers', $capturedOptions);
        self::assertArrayHasKey('authorization', $capturedOptions['normalized_headers']);
        self::assertStringContainsString('oauth_callback="oob"', $capturedOptions['normalized_headers']['authorization'][0]);
    }
}