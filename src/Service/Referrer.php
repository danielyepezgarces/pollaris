<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Referrer
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Return the referrer URL of the current request.
     *
     * The referrer is always an internal URL or path. If the referrer found in
     * the header targets an external domain, the method returns the path to
     * the homepage.
     */
    public function get(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return $this->urlGenerator->generate('home');
        }

        $referrer = $request->headers->get('Referer');
        $actualSchemeAndHost = $request->getSchemeAndHttpHost();

        if ($referrer && $this->isSafeUrl($referrer, $actualSchemeAndHost)) {
            return $referrer;
        } else {
            return $this->urlGenerator->generate('home');
        }
    }

    /**
     * Return whether the given URL targets the current server.
     *
     * This is used to avoid attacks redirecting users to an external URL.
     *
     * Note that it doesn't check if the path is a valid route.
     */
    private function isSafeUrl(string $url, string $actualSchemeAndHost): bool
    {
        $isHttpUrl = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
        $isAbsolutePath = str_starts_with($url, '/') && !str_starts_with($url, '//');

        if ($isHttpUrl) {
            // If the URL is an HTTP(S) URL, make sure that it targets the application URL.
            $appUrl = rtrim($actualSchemeAndHost, '/') . '/';
            return str_starts_with($url, $appUrl);
        } elseif ($isAbsolutePath) {
            return true;
        } else {
            return false;
        }
    }
}
