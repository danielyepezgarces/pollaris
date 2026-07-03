<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\TranslateWiki;

class TranslationConverter
{
    /**
     * Converts Symfony ICU plural syntax in a string to MediaWiki plural syntax.
     */
    public static function convertIcuToMediaWiki(string $text, string $locale): string
    {
        $offset = 0;
        while (($pos = strpos($text, '{', $offset)) !== false) {
            $nextComma = strpos($text, ',', $pos);
            if ($nextComma === false) {
                $offset = $pos + 1;
                continue;
            }
            $varName = trim(substr($text, $pos + 1, $nextComma - $pos - 1));
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $varName)) {
                $offset = $pos + 1;
                continue;
            }
            
            $nextComma2 = strpos($text, ',', $nextComma + 1);
            if ($nextComma2 === false) {
                $offset = $pos + 1;
                continue;
            }
            $type = trim(substr($text, $nextComma + 1, $nextComma2 - $nextComma - 1));
            if ($type !== 'plural') {
                $offset = $pos + 1;
                continue;
            }
            
            // Find matching closing brace
            $braceCount = 1;
            $i = $nextComma2 + 1;
            $length = strlen($text);
            while ($i < $length && $braceCount > 0) {
                if ($text[$i] === '{') {
                    $braceCount++;
                } elseif ($text[$i] === '}') {
                    $braceCount--;
                }
                $i++;
            }
            if ($braceCount > 0) {
                $offset = $pos + 1;
                continue;
            }
            
            $pluralBlock = substr($text, $pos, $i - $pos);
            $inner = substr($text, $nextComma2 + 1, $i - 1 - ($nextComma2 + 1));
            
            $categories = self::parseIcuCategories($inner);
            $converted = self::buildMediaWikiPlural($categories, $locale);
            
            $text = substr_replace($text, $converted, $pos, strlen($pluralBlock));
            $offset = $pos + strlen($converted);
        }
        return $text;
    }

    /**
     * Converts MediaWiki plural syntax in a string to Symfony ICU plural syntax.
     */
    public static function convertMediaWikiToIcu(string $text, string $locale, ?string $sourceEnglishMessage): string
    {
        $variables = $sourceEnglishMessage ? self::getIcuPluralVariables($sourceEnglishMessage) : [];
        $varIndex = 0;
        
        $offset = 0;
        while (($pos = stripos($text, '{{PLURAL:$1|', $offset)) !== false) {
            // Find matching '}}'
            $braceCount = 2; // we already have '{{'
            $i = $pos + strlen('{{PLURAL:$1|');
            $length = strlen($text);
            
            while ($i < $length && $braceCount > 0) {
                if ($text[$i] === '{') {
                    $braceCount++;
                } elseif ($text[$i] === '}') {
                    $braceCount--;
                }
                $i++;
            }
            if ($braceCount > 0) {
                $offset = $pos + 2;
                continue;
            }
            
            $pluralBlock = substr($text, $pos, $i - $pos);
            $inner = substr($text, $pos + strlen('{{PLURAL:$1|'), $i - 2 - ($pos + strlen('{{PLURAL:$1|')));
            
            $categories = self::parseMediaWikiCategories($inner, $locale);
            $varName = $variables[$varIndex] ?? 'count';
            $varIndex++;
            
            $converted = self::buildIcuPlural($varName, $categories);
            
            $text = substr_replace($text, $converted, $pos, strlen($pluralBlock));
            $offset = $pos + strlen($converted);
        }
        return $text;
    }

    /**
     * Helper to parse ICU categories and their corresponding text blocks.
     */
    private static function parseIcuCategories(string $inner): array
    {
        $i = 0;
        $length = strlen($inner);
        $categories = [];
        while ($i < $length) {
            while ($i < $length && ctype_space($inner[$i])) {
                $i++;
            }
            if ($i >= $length) break;
            
            $startCat = $i;
            while ($i < $length && !ctype_space($inner[$i]) && $inner[$i] !== '{') {
                $i++;
            }
            $category = substr($inner, $startCat, $i - $startCat);
            
            while ($i < $length && ctype_space($inner[$i])) {
                $i++;
            }
            if ($i < $length && $inner[$i] === '{') {
                $i++;
                $startText = $i;
                $braceCount = 1;
                while ($i < $length && $braceCount > 0) {
                    if ($inner[$i] === '{') {
                        $braceCount++;
                    } elseif ($inner[$i] === '}') {
                        $braceCount--;
                    }
                    $i++;
                }
                $text = substr($inner, $startText, $i - 1 - $startText);
                $categories[$category] = $text;
            } else {
                break;
            }
        }
        return $categories;
    }

    /**
     * Helper to build a MediaWiki plural syntax string.
     */
    private static function buildMediaWikiPlural(array $categories, string $locale): string
    {
        $parts = [];
        // 1. Add explicit numeric categories (like =0)
        foreach ($categories as $cat => $text) {
            if (str_starts_with($cat, '=')) {
                $num = substr($cat, 1);
                $textWithPlaceholder = str_replace('#', '$1', $text);
                $parts[] = $num . '=' . $textWithPlaceholder;
            }
        }

        // 2. Add positional categories in language's plural order
        $orderedCats = self::getPluralCategoriesForLocale($locale);
        foreach ($orderedCats as $cat) {
            $text = '';
            if (isset($categories[$cat])) {
                $text = $categories[$cat];
            } else {
                // fallback
                if ($cat === 'few' || $cat === 'many') {
                    $text = $categories['other'] ?? ($categories['many'] ?? ($categories['few'] ?? ($categories['one'] ?? '')));
                } else {
                    $text = $categories['other'] ?? ($categories['one'] ?? '');
                }
            }
            $parts[] = str_replace('#', '$1', $text);
        }

        return '{{PLURAL:$1|' . implode('|', $parts) . '}}';
    }

    /**
     * Helper to parse MediaWiki categories.
     */
    private static function parseMediaWikiCategories(string $inner, string $locale): array
    {
        $parts = [];
        $current = '';
        $braceCount = 0;
        $length = strlen($inner);
        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];
            if ($char === '{') {
                $braceCount++;
                $current .= $char;
            } elseif ($char === '}') {
                $braceCount--;
                $current .= $char;
            } elseif ($char === '|' && $braceCount === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $parts[] = $current;
        
        $categories = [];
        $positionalParts = [];
        
        foreach ($parts as $part) {
            if (preg_match('/^([0-9]+)=(.*)$/s', $part, $matches)) {
                $num = $matches[1];
                $val = $matches[2];
                $categories['=' . $num] = $val;
            } else {
                $positionalParts[] = $part;
            }
        }
        
        $orderedCats = self::getPluralCategoriesForLocale($locale);
        foreach ($positionalParts as $idx => $part) {
            if (isset($orderedCats[$idx])) {
                $cat = $orderedCats[$idx];
                $categories[$cat] = $part;
            } else {
                $categories['other'] = $part;
            }
        }
        
        foreach ($categories as $cat => $text) {
            $categories[$cat] = str_replace('$1', '#', $text);
        }
        
        return $categories;
    }

    /**
     * Helper to build an ICU plural syntax string.
     */
    private static function buildIcuPlural(string $varName, array $categories): string
    {
        $parts = [];
        if (!isset($categories['other'])) {
            $categories['other'] = $categories['many'] ?? ($categories['few'] ?? ($categories['one'] ?? ''));
        }
        
        foreach ($categories as $cat => $text) {
            $parts[] = $cat . ' {' . $text . '}';
        }
        
        return '{' . $varName . ', plural, ' . implode(' ', $parts) . '}';
    }

    /**
     * Finds the names of the count variables of ICU plural blocks in English source text.
     */
    private static function getIcuPluralVariables(string $text): array
    {
        $variables = [];
        $offset = 0;
        while (($pos = strpos($text, '{', $offset)) !== false) {
            $nextComma = strpos($text, ',', $pos);
            if ($nextComma === false) {
                $offset = $pos + 1;
                continue;
            }
            $varName = trim(substr($text, $pos + 1, $nextComma - $pos - 1));
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $varName)) {
                $offset = $pos + 1;
                continue;
            }
            $nextComma2 = strpos($text, ',', $nextComma + 1);
            if ($nextComma2 === false) {
                $offset = $pos + 1;
                continue;
            }
            $type = trim(substr($text, $nextComma + 1, $nextComma2 - $nextComma - 1));
            if ($type !== 'plural') {
                $offset = $pos + 1;
                continue;
            }
            
            $braceCount = 1;
            $i = $nextComma2 + 1;
            $length = strlen($text);
            while ($i < $length && $braceCount > 0) {
                if ($text[$i] === '{') {
                    $braceCount++;
                } elseif ($text[$i] === '}') {
                    $braceCount--;
                }
                $i++;
            }
            if ($braceCount > 0) {
                $offset = $pos + 1;
                continue;
            }
            
            $variables[] = $varName;
            $offset = $i;
        }
        return $variables;
    }

    /**
     * Gets the order of plural categories in MediaWiki format by language/locale.
     */
    private static function getPluralCategoriesForLocale(string $locale): array
    {
        $lang = explode('_', $locale)[0];
        switch ($lang) {
            case 'cs':
                return ['one', 'few', 'other'];
            case 'uk':
                return ['one', 'few', 'many'];
            default:
                return ['one', 'other'];
        }
    }
}
