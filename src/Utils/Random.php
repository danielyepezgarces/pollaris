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

class Random
{
    public static function hex(int $length): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be a positive integer');
        }

        $string = '';
        $alphabet = '0123456789abcdef';
        $alphamax = strlen($alphabet) - 1;

        for ($i = 0; $i < $length; ++$i) {
            $string .= $alphabet[random_int(0, $alphamax)];
        }

        return $string;
    }
}
