<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

if (isset($_SERVER['TRUSTED_PROXIES'])) {
    Request::setTrustedProxies(
        explode(',', $_SERVER['TRUSTED_PROXIES']),
        Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_HOST
    );
}

if (isset($_SERVER['TRUSTED_HOSTS'])) {
    Request::setTrustedHosts([$_SERVER['TRUSTED_HOSTS']]);
}

return function (array $context): \App\Kernel {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
