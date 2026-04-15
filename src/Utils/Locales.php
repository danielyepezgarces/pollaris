<?php

// This file is part of Pollaris.
// Copyright 2022-2024 Probesys (Bileto)
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

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
