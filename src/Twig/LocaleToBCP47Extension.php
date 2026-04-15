<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

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
