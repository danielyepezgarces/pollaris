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

namespace App\Utils;

class Locales
{
    public const DEFAULT_LOCALE = 'en_GB';

    /**
     * @return array<string, string>
     */
    public static function getSupportedLanguages(): array
    {
        return [
            'en_GB' => 'English',
            'cs' => 'Čeština',
            'de' => 'Deutsch',
            'es' => 'Español',
            'fr_FR' => 'Français',
            'gl' => 'Galego',
            'hu' => 'Magyar',
            'it' => 'Italiano',
            'pt' => 'Português',
            'pt_PT' => 'Português (Portugal)',
            'pt_BR' => 'Português (Brasil)',
            'oc' => 'Occitan',
            'kab' => 'Taqbaylit',
            'uk' => 'Українська мова',
        ];
    }

    /**
     * @return string[]
     */
    public static function getSupportedCodes(): array
    {
        return array_keys(self::getSupportedLanguages());
    }

    public static function isAvailable(string $locale): bool
    {
        return in_array($locale, self::getSupportedCodes());
    }
}
