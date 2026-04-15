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

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class AssetsMtimeStrategy implements VersionStrategyInterface
{
    public function __construct(
        private string $publicPath,
    ) {
    }

    public function getVersion(string $path): string
    {
        $fullpath = "{$this->publicPath}/{$path}";
        $modicationTime = @filemtime($fullpath);
        if ($modicationTime) {
            return (string) $modicationTime;
        } else {
            return '';
        }
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);
        return "{$path}?v={$version}";
    }
}
