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

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WikimediaOAuth
{
    public const SESSION_REQUEST_TOKEN_KEY = 'wikimedia_oauth_request_token_key';
    public const SESSION_REQUEST_TOKEN_SECRET = 'wikimedia_oauth_request_token_secret';

    public function __construct(
        private HttpClientInterface $httpClient,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(bool:WIKIMEDIA_OAUTH_ENABLED)%')]
        private bool $enabled,
        #[Autowire('%env(string:WIKIMEDIA_OAUTH_BASE_URL)%')]
        private string $baseUrl,
        #[Autowire('%env(string:WIKIMEDIA_OAUTH_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(string:WIKIMEDIA_OAUTH_CLIENT_SECRET)%')]
        private string $clientSecret,
        #[Autowire('%app.name%')]
        private string $appName,
        #[Autowire('%env(string:APP_BASE_URL)%')]
        private string $appBaseUrl,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%env(default::string:WIKIMEDIA_OAUTH_USER_AGENT)%')]
        private ?string $customUserAgent = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->enabled
            && $this->baseUrl !== ''
            && $this->clientId !== ''
            && $this->clientSecret !== '';
    }

    public function buildAuthorizationUrl(SessionInterface $session): string
    {
        $requestToken = $this->fetchRequestToken();

        $session->set(self::SESSION_REQUEST_TOKEN_KEY, $requestToken['key']);
        $session->set(self::SESSION_REQUEST_TOKEN_SECRET, $requestToken['secret']);

        return sprintf('%s?%s', $this->getIndexPhpUrl(), http_build_query([
            'title' => 'Special:OAuth/authenticate',
            'oauth_token' => $requestToken['key'],
            'oauth_consumer_key' => $this->clientId,
        ]));
    }

    public function hasValidCallback(SessionInterface $session, string $oauthToken, string $oauthVerifier): bool
    {
        $requestToken = $session->get(self::SESSION_REQUEST_TOKEN_KEY);
        $requestTokenSecret = $session->get(self::SESSION_REQUEST_TOKEN_SECRET);

        return is_string($requestToken)
            && $requestToken !== ''
            && is_string($requestTokenSecret)
            && $requestTokenSecret !== ''
            && hash_equals($requestToken, $oauthToken)
            && $oauthVerifier !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchProfile(SessionInterface $session, string $oauthVerifier): array
    {
        $requestToken = (string) $session->get(self::SESSION_REQUEST_TOKEN_KEY, '');
        $requestTokenSecret = (string) $session->get(self::SESSION_REQUEST_TOKEN_SECRET, '');

        $session->remove(self::SESSION_REQUEST_TOKEN_KEY);
        $session->remove(self::SESSION_REQUEST_TOKEN_SECRET);

        if ($requestToken === '' || $requestTokenSecret === '') {
            throw new \RuntimeException('Missing Wikimedia request token in session.');
        }

        $accessToken = $this->fetchAccessToken($requestToken, $requestTokenSecret, $oauthVerifier);

        return $this->identify($accessToken['key'], $accessToken['secret']);
    }

    /**
     * @return array{key: string, secret: string}
     */
    private function fetchRequestToken(): array
    {
        $authParameters = $this->buildOauthParameters([
            'oauth_callback' => 'oob',
        ]);

        $response = $this->sendSignedPostRequest(
            [
                'title' => 'Special:OAuth/initiate',
            ],
            $authParameters,
            '',
        );

        $payload = $this->parseUrlEncodedResponse($response);

        return $this->extractTokenPair($payload, 'request');
    }

    /**
     * @return array{key: string, secret: string}
     */
    private function fetchAccessToken(string $requestToken, string $requestTokenSecret, string $oauthVerifier): array
    {
        $authParameters = $this->buildOauthParameters([
            'oauth_token' => $requestToken,
            'oauth_verifier' => $oauthVerifier,
        ]);

        $response = $this->sendSignedPostRequest(
            [
                'title' => 'Special:OAuth/token',
            ],
            $authParameters,
            $requestTokenSecret,
        );

        $payload = $this->parseUrlEncodedResponse($response);

        return $this->extractTokenPair($payload, 'access');
    }

    /**
     * @return array<string, mixed>
     */
    private function identify(string $accessToken, string $accessTokenSecret): array
    {
        $authParameters = $this->buildOauthParameters([
            'oauth_token' => $accessToken,
        ]);

        $response = $this->sendSignedPostRequest(
            [
                'title' => 'Special:OAuth/identify',
            ],
            $authParameters,
            $accessTokenSecret,
        );

        $payload = $this->decodeIdentityJwt(trim($response));
        $payload['username'] = $payload['username'] ?? null;
        $payload['sub'] = isset($payload['sub']) ? (string) $payload['sub'] : ($payload['username'] ?? null);

        return $payload;
    }

    /**
     * @param array<string, string> $queryParameters
     * @param array<string, string> $authParameters
     */
    private function sendSignedPostRequest(
        array $queryParameters,
        array $authParameters,
        string $tokenSecret,
    ): string {
        $signatureParameters = $authParameters + $queryParameters;
        $authParameters['oauth_signature'] = $this->signRequest(
            'POST',
            $this->getIndexPhpUrl(),
            $signatureParameters,
            $tokenSecret,
        );

        try {
            $response = $this->httpClient->request('POST', $this->getIndexPhpUrl(), [
                'query' => $queryParameters,
                'headers' => [
                    'Authorization' => $this->buildAuthorizationHeader($authParameters),
                    'User-Agent' => $this->getUserAgent(),
                ],
            ]);

            return $response->getContent();
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('Unable to complete Wikimedia OAuth request.', previous: $exception);
        }
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function buildOauthParameters(array $overrides = []): array
    {
        return $overrides + [
            'oauth_consumer_key' => $this->clientId,
            'oauth_nonce' => $this->generateNonce(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_version' => '1.0',
        ];
    }

    /**
     * @param array<string, string> $parameters
     */
    private function signRequest(
        string $method,
        string $url,
        array $parameters,
        string $tokenSecret,
    ): string {
        $normalizedUrl = $this->normalizeUrl($url);
        $normalizedParams = $this->normalizeParameters($parameters);
        $signatureBase = implode('&', [
            rawurlencode(strtoupper($method)),
            rawurlencode($normalizedUrl),
            rawurlencode($normalizedParams),
        ]);

        $signingKey = rawurlencode($this->clientSecret) . '&' . rawurlencode($tokenSecret);

        return base64_encode(hash_hmac('sha1', $signatureBase, $signingKey, true));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function normalizeParameters(array $parameters): string
    {
        $pairs = [];

        foreach ($parameters as $name => $value) {
            $pairs[] = [
                rawurlencode($name),
                rawurlencode($value),
            ];
        }

        usort($pairs, function (array $left, array $right): int {
            return [$left[0], $left[1]] <=> [$right[0], $right[1]];
        });

        return implode('&', array_map(
            fn (array $pair): string => "{$pair[0]}={$pair[1]}",
            $pairs,
        ));
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            throw new \RuntimeException('Invalid Wikimedia OAuth URL.');
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? null;
        $path = $parts['path'] ?? '';

        if ($host === '') {
            throw new \RuntimeException('Invalid Wikimedia OAuth host.');
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;
        $normalizedHost = $port !== null && $port !== $defaultPort
            ? "{$host}:{$port}"
            : $host;

        return "{$scheme}://{$normalizedHost}{$path}";
    }

    /**
     * @param array<string, string> $parameters
     */
    private function buildAuthorizationHeader(array $parameters): string
    {
        $parts = [];

        foreach ($parameters as $name => $value) {
            $parts[] = rawurlencode($name) . '="' . rawurlencode($value) . '"';
        }

        return 'OAuth ' . implode(', ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function parseUrlEncodedResponse(string $content): array
    {
        if (str_starts_with($content, 'Error: ')) {
            throw new \RuntimeException(substr($content, strlen('Error: ')));
        }

        parse_str($content, $payload);

        return array_map(
            fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            $payload,
        );
    }

    /**
     * @param array<string, string> $payload
     *
     * @return array{key: string, secret: string}
     */
    private function extractTokenPair(array $payload, string $kind): array
    {
        $tokenKey = $payload['oauth_token'] ?? null;
        $tokenSecret = $payload['oauth_token_secret'] ?? null;

        if (!is_string($tokenKey) || $tokenKey === '' || !is_string($tokenSecret) || $tokenSecret === '') {
            throw new \RuntimeException("Wikimedia did not return a valid {$kind} token.");
        }

        return [
            'key' => $tokenKey,
            'secret' => $tokenSecret,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeIdentityJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException("Could not read response from 'Special:OAuth/identify'.");
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        if (!is_array($header) || ($header['typ'] ?? null) !== 'JWT' || ($header['alg'] ?? null) !== 'HS256') {
            throw new \RuntimeException('Invalid Wikimedia identity token header.');
        }

        $expectedSignature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $this->clientSecret, true);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new \RuntimeException('Unable to verify Wikimedia identity token signature.');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid Wikimedia identity token payload.');
        }

        return $payload;
    }

    private function getIndexPhpUrl(): string
    {
        $baseUrl = rtrim($this->baseUrl, '/');

        if (str_ends_with($baseUrl, '/w/index.php')) {
            return $baseUrl;
        }

        if (str_ends_with($baseUrl, '/w/rest.php/oauth')) {
            return substr($baseUrl, 0, -strlen('/w/rest.php/oauth')) . '/w/index.php';
        }

        if (str_ends_with($baseUrl, '/wiki/Special:OAuth')) {
            return substr($baseUrl, 0, -strlen('/wiki/Special:OAuth')) . '/w/index.php';
        }

        throw new \RuntimeException('Unsupported Wikimedia OAuth base URL.');
    }

    private function getRedirectUri(): string
    {
        return $this->urlGenerator->generate('login wikimedia callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Unable to decode Wikimedia identity token.');
        }

        return $decoded;
    }

    private function getUserAgent(): string
    {
        if (($this->customUserAgent ?? '') !== '') {
            return $this->customUserAgent;
        }

        $versionFile = sprintf('%s/VERSION.txt', rtrim($this->projectDir, '/'));
        $version = 'dev';

        if (is_file($versionFile)) {
            $resolvedVersion = trim((string) file_get_contents($versionFile));

            if ($resolvedVersion !== '') {
                $version = $resolvedVersion;
            }
        }

        $contact = $this->appBaseUrl !== '' ? $this->appBaseUrl : $this->getRedirectUri();

        return sprintf('%s/%s (%s; Wikimedia OAuth login)', $this->appName, $version, $contact);
    }
}
