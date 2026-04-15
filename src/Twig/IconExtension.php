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

use App\Utils;
use Symfony\Component\Asset;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

class IconExtension
{
    public function __construct(
        #[Autowire('%app.public_directory%')]
        private string $pathToPublic,
    ) {
    }

    #[AsTwigFunction('icon', isSafe: ['html'])]
    public function icon(string $iconName, string $additionalClassNames = ''): string
    {
        $iconName = htmlspecialchars($iconName);
        $additionalClassNames = htmlspecialchars($additionalClassNames);

        $assetStrategy = new Utils\AssetsMtimeStrategy($this->pathToPublic);
        $assetPackage = new Asset\Package($assetStrategy);

        $iconsUrl = $assetPackage->getUrl('/icons.svg');

        $classNames = "icon icon--{$iconName}";
        if ($additionalClassNames) {
            $classNames .= " {$additionalClassNames}";
        }

        $svg = "<svg class=\"{$classNames}\" aria-hidden=\"true\" width=\"24\" height=\"24\">";
        $svg .= "<use xlink:href=\"{$iconsUrl}#{$iconName}\"/>";
        $svg .= '</svg>';

        return $svg;
    }
}
