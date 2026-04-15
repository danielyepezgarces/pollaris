<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

class MarkdownExtension
{
    public function __construct(
        private MyParsedown $converter,
    ) {
        $this->converter->setSafeMode(true);
    }

    #[AsTwigFilter('markdown', isSafe: ['all'])]
    public function markdown(string $text): string
    {
        return $this->converter->text($text);
    }
}
