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

namespace App\Twig;

use App\Service;
use App\Utils;
use Twig\Attribute\AsTwigFilter;

class DateFormatterExtension
{
    public function __construct(
        private Service\DateTranslator $dateTranslator,
    ) {
    }

    #[AsTwigFilter('dateTrans')]
    public function dateTrans(
        \DateTimeInterface $date,
        string $format = 'dd MMM yyyy, HH:mm',
        ?string $timezone = null,
    ): string
    {
        return $this->dateTranslator->format($date, $format, $timezone);
    }

    #[AsTwigFilter('dateIso')]
    public function dateIso(\DateTimeInterface $date, ?string $timezone = null): string
    {
        if ($timezone !== null) {
            $date = \DateTimeImmutable::createFromInterface($date)
                ->setTimezone(new \DateTimeZone($timezone));
        }

        return $date->format(\DateTimeInterface::ATOM);
    }

    #[AsTwigFilter('dateFull')]
    public function dateFull(
        \DateTimeInterface $date,
        bool $fullMonth = false,
        bool $cleverYear = false,
        ?string $timezone = null,
    ): string
    {
        $today = Utils\Time::relative('today');
        $currentYear = $timezone === null
            ? $today->format('Y')
            : $today->setTimezone(new \DateTimeZone($timezone))->format('Y');
        $dateYear = $timezone === null
            ? $date->format('Y')
            : \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone($timezone))->format('Y');

        $format = 'dd';

        if ($fullMonth) {
            $format .= ' MMMM';
        } else {
            $format .= ' MMM';
        }

        if (!$cleverYear || $currentYear !== $dateYear) {
            $format .= ' yyyy';
        }

        $format .= ', HH:mm';

        return $this->dateTrans($date, $format, $timezone);
    }

    #[AsTwigFilter('dateShort')]
    public function dateShort(
        \DateTimeInterface $date,
        bool $fullMonth = false,
        bool $cleverYear = false,
        ?string $timezone = null,
    ): string
    {
        $today = Utils\Time::relative('today');
        $currentYear = $timezone === null
            ? $today->format('Y')
            : $today->setTimezone(new \DateTimeZone($timezone))->format('Y');
        $dateYear = $timezone === null
            ? $date->format('Y')
            : \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone($timezone))->format('Y');

        $format = 'dd';

        if ($fullMonth) {
            $format .= ' MMMM';
        } else {
            $format .= ' MMM';
        }

        if (!$cleverYear || $currentYear !== $dateYear) {
            $format .= ' yyyy';
        }

        return $this->dateTrans($date, $format, $timezone);
    }
}
