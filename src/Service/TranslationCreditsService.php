<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Intl\Locales;

class TranslationCreditsService
{
    private const CUSTOM_LANGUAGE_NAMES = [
        'kab' => 'Taqbaylit (Kabyle)',
        'oc' => 'Occitan',
        'ps' => 'پښتو (Pashto)',
        'lb' => 'Lëtzebuergesch (Luxembourgish)',
        'ne' => 'नेपाली (Nepali)',
        'gl' => 'Galego (Galician)',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {
    }

    /**
     * @return array<string, array{username: string, profileUrl: string, languages: array<int, array{code: string, name: string}>}>
     */
    public function getTranslators(?string $displayLocale = null): array
    {
        $translatewikiDir = $this->projectDir . '/translatewiki';
        $files = glob($translatewikiDir . '/*.json') ?: [];

        $translators = [];

        foreach ($files as $file) {
            $filename = basename($file);
            if (!preg_match('/^(?:messages|validators)\.([a-zA-Z0-9_-]+)\.json$/', $filename, $matches)) {
                continue;
            }

            $locale = $matches[1];
            $content = json_decode((string) file_get_contents($file), true);

            if (!is_array($content) || !isset($content['@metadata']['authors']) || !is_array($content['@metadata']['authors'])) {
                continue;
            }

            foreach ($content['@metadata']['authors'] as $author) {
                if (!is_string($author)) {
                    continue;
                }

                $author = trim($author);
                if ($author === '') {
                    continue;
                }

                // Normalise key case-insensitively for de-duplication while preserving author display name
                $normKey = mb_strtolower($author);

                if (!isset($translators[$normKey])) {
                    $encodedUsername = rawurlencode(str_replace(' ', '_', $author));
                    $translators[$normKey] = [
                        'username' => $author,
                        'profileUrl' => "https://translatewiki.net/wiki/User:{$encodedUsername}",
                        'languages' => [],
                    ];
                }

                $translators[$normKey]['languages'][$locale] = true;
            }
        }

        // Format and resolve human-readable language names
        foreach ($translators as &$translator) {
            $langList = [];
            $localeCodes = array_keys($translator['languages']);
            sort($localeCodes);

            foreach ($localeCodes as $code) {
                $langName = $this->getLanguageName($code, $displayLocale);
                $langList[] = [
                    'code' => $code,
                    'name' => $langName,
                ];
            }

            $translator['languages'] = $langList;
        }
        unset($translator);

        // Sort alphabetically by username (case-insensitive)
        uasort($translators, static fn(array $a, array $b): int => strcasecmp($a['username'], $b['username']));

        return $translators;
    }

    /**
     * @return array{translatorsCount: int, languagesCount: int}
     */
    public function getStats(?string $displayLocale = null): array
    {
        $translators = $this->getTranslators($displayLocale);
        $languages = [];

        foreach ($translators as $translator) {
            foreach ($translator['languages'] as $lang) {
                $languages[$lang['code']] = true;
            }
        }

        return [
            'translatorsCount' => count($translators),
            'languagesCount' => count($languages),
        ];
    }

    public function getLanguageName(string $locale, ?string $displayLocale = null): string
    {
        if (isset(self::CUSTOM_LANGUAGE_NAMES[$locale])) {
            return self::CUSTOM_LANGUAGE_NAMES[$locale];
        }

        if (Locales::exists($locale)) {
            return Locales::getName($locale, $displayLocale ?? 'en');
        }

        return $locale;
    }
}
