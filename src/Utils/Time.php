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

namespace App\Utils;

class Time
{
    private static ?\DateTimeImmutable $freezedNow = null;

    public static function getServerTimezoneName(): string
    {
        return date_default_timezone_get();
    }

    public static function isValidTimezone(?string $timezone): bool
    {
        if ($timezone === null || $timezone === '') {
            return false;
        }

        try {
            new \DateTimeZone($timezone);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public static function resolvePollTimezone(
        string $timezoneMode,
        ?string $browserTimezone = null,
        ?string $currentTimezone = null,
    ): string {
        if ($timezoneMode === 'browser' && self::isValidTimezone($browserTimezone)) {
            return $browserTimezone;
        }

        if ($timezoneMode === 'browser' && self::isValidTimezone($currentTimezone)) {
            return $currentTimezone;
        }

        return self::getServerTimezoneName();
    }

    public static function normalizeDateForTimezone(
        ?\DateTimeImmutable $date,
        string $timezone,
    ): ?\DateTimeImmutable {
        if ($date === null) {
            return null;
        }

        return new \DateTimeImmutable(
            $date->format('Y-m-d 00:00:00'),
            new \DateTimeZone($timezone),
        );
    }

    public static function now(): \DateTimeImmutable
    {
        if (self::$freezedNow) {
            return self::$freezedNow;
        } else {
            return new \DateTimeImmutable('now');
        }
    }

    /**
     * @see https://www.php.net/manual/datetime.modify.php
     * @see https://www.php.net/manual/datetime.formats.relative.php
     */
    public static function relative(string $modifier): \DateTimeImmutable
    {
        return self::now()->modify($modifier);
    }

    /**
     * Return a datetime from the future.
     *
     * @see https://www.php.net/manual/en/datetime.formats.relative.php
     */
    public static function fromNow(int $number, string $unit): \DateTimeImmutable
    {
        return self::relative("+{$number} {$unit}");
    }

    /**
     * Return a datetime from the past.
     *
     * @see https://www.php.net/manual/en/datetime.formats.relative.php
     */
    public static function ago(int $number, string $unit): \DateTimeImmutable
    {
        return self::relative("-{$number} {$unit}");
    }

    public static function freeze(\DateTimeImmutable $datetime): void
    {
        self::$freezedNow = $datetime;
    }

    public static function unfreeze(): void
    {
        self::$freezedNow = null;
    }
}
