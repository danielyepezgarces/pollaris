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

/**
 * An extension to the Parsedown library.
 *
 * It renders a very limited set of HTML tags. It generates "safe" HTML by
 * default.
 */
class MyParsedown extends \Parsedown
{
    public const ALLOWED_ELEMENTS = [
        'a',
        'blockquote',
        'br',
        'del',
        'em',
        'li',
        'ol',
        'p',
        'strong',
        'ul',
    ];

    public function __construct()
    {
        $this->setSafeMode(true);
        $this->setBreaksEnabled(true);
    }

    /**
     * @see \Parsedown::element
     *
     * @param mixed[] $element
     */
    protected function element(array $element): string
    {
        $name = $element['name'] ?? '';
        $text = $element['text'] ?? '';
        if ($name === '' || in_array($name, self::ALLOWED_ELEMENTS)) {
            return parent::element($element);
        } elseif (is_array($text)) {
            return self::element($text);
        } elseif (is_string($text)) {
            return self::escape($text, true);
        } else {
            return '';
        }
    }
}
