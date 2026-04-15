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

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

class LocaleToBCP47Extension
{
    #[AsTwigFilter('locale_to_bcp47')]
    public function localeToBCP47(string $locale): string
    {
        $splittedLocale = explode('_', $locale, 2);

        if (count($splittedLocale) === 1) {
            return $splittedLocale[0];
        }

        return $splittedLocale[0] . '-' . strtoupper($splittedLocale[1]);
    }
}
