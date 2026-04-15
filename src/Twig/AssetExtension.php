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

use App\Utils;
use Symfony\Component\Asset;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

class AssetExtension
{
    public function __construct(
        private string $pathToAssets,
        #[Autowire('%app.public_directory%')]
        private string $pathToPublic,
    ) {
    }

    #[AsTwigFunction('esbuild_asset')]
    public function esbuildAsset(string $assetPath): string
    {
        $assetStrategy = new Utils\AssetsMtimeStrategy($this->pathToPublic);
        $assetPackage = new Asset\Package($assetStrategy);

        $assetPathname = "/{$this->pathToAssets}/{$assetPath}";

        return $assetPackage->getUrl($assetPathname);
    }

    #[AsTwigFunction('asset_exists')]
    public function assetExists(string $assetPath): bool
    {
        $assetPathname = "{$this->pathToPublic}/{$assetPath}";
        return file_exists($assetPathname);
    }
}
