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

    #[AsTwigFilter('wiki_or_markdown', isSafe: ['all'])]
    public function wikiOrMarkdown(?string $text): string
    {
        if (null === $text || '' === $text) {
            return '';
        }

        // 1. Obtener HTML seguro del parseador Markdown (escapa scripts y HTML inseguro)
        $html = $this->converter->text($text);

        // 2. Parsear enlaces Wikitext con etiqueta: [[WikiPage|Label]]
        $html = preg_replace_callback('/\[\[([^|\]]+)\|([^\]]+)\]\]/', function($matches) {
            $url = 'https://meta.wikimedia.org/wiki/' . str_replace(' ', '_', trim($matches[1]));
            return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), htmlspecialchars(trim($matches[2]), ENT_QUOTES, 'UTF-8'));
        }, $html);

        // 3. Parsear enlaces Wikitext simples: [[WikiPage]]
        $html = preg_replace_callback('/\[\[([^|\]]+)\]\]/', function($matches) {
            $page = trim($matches[1]);
            $url = 'https://meta.wikimedia.org/wiki/' . str_replace(' ', '_', $page);
            return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), htmlspecialchars($page, ENT_QUOTES, 'UTF-8'));
        }, $html);

        // 4. Negritas de Wikitext: '''texto'''
        $html = preg_replace("/'''(.*?)'''/", '<strong>$1</strong>', $html);

        // 5. Itálicas de Wikitext: ''texto''
        $html = preg_replace("/''(.*?)''/", '<em>$1</em>', $html);

        return $html;
    }
}
