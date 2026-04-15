<?php

// This file is part of Pollaris.
// Copyright 2022-2024 Probesys (Bileto)
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

use Symfony\Component\HttpFoundation\RequestStack;

class DateTranslator
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function format(
        \DateTimeInterface $date,
        string $format = 'dd MMM Y, HH:mm',
        ?string $timezone = null,
    ): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            throw new \Exception('Cannot translate the date (request is null)');
        }

        $currentLocale = $request->getLocale();

        $formatter = new \IntlDateFormatter(
            $currentLocale,
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            $timezone,
            null,
            $format
        );

        $translatedDate = $formatter->format($date);
        if ($translatedDate === false) {
            throw new \Exception("Cannot translate the date (format: {$format})");
        }

        return $translatedDate;
    }
}
