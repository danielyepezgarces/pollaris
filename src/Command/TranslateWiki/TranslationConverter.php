<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\TranslateWiki;

class TranslationConverter
{
    /**
     * Converts Symfony ICU plural syntax and named placeholders (both {var} and __var__) in a string to MediaWiki format.
     */
    public static function convertIcuToMediaWiki(string $text, string $locale, ?string $sourceEnglishMessage = null): string
    {
        $variables = self::getPlaceholderVariables($sourceEnglishMessage ?? $text);
        
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
            
            $varIndex = array_search($varName, $variables, true);
            $varPlaceholderNum = ($varIndex !== false) ? ($varIndex + 1) : 1;
            
            $categories = self::parseIcuCategories($inner);
            $converted = self::buildMediaWikiPlural($categories, $locale, $varPlaceholderNum);
            
            $text = substr_replace($text, $converted, $pos, strlen($pluralBlock));
            $offset = $pos + strlen($converted);
        }
        
        // Convert any remaining named simple placeholders (e.g. {limit}, {months}, or __date__) to $1, $2, etc.
        foreach ($variables as $index => $var) {
            if (str_starts_with($var, '__') && str_ends_with($var, '__')) {
                $placeholder = $var;
            } else {
                $placeholder = '{' . $var . '}';
            }
            $positional = '$' . ($index + 1);
            $text = str_replace($placeholder, $positional, $text);
        }
        
        return $text;
    }

    /**
     * Converts MediaWiki format (plural syntax and positional placeholders) to Symfony ICU.
     */
    public static function convertMediaWikiToIcu(string $text, string $locale, ?string $sourceEnglishMessage): string
    {
        $variables = $sourceEnglishMessage ? self::getPlaceholderVariables($sourceEnglishMessage) : [];
        
        $offset = 0;
        while (($pos = stripos($text, '{{PLURAL:$', $offset)) !== false) {
            $i = $pos + strlen('{{PLURAL:$');
            $length = strlen($text);
            $numStr = '';
            while ($i < $length && is_numeric($text[$i])) {
                $numStr .= $text[$i];
                $i++;
            }
            if ($numStr === '' || $i >= $length || $text[$i] !== '|') {
                $offset = $pos + 10;
                continue;
            }
            
            $varPlaceholderNum = (int)$numStr;
            $i++; // skip '|'
            
            // Find matching '}}'
            $braceCount = 2; // we already have '{{'
            $startInner = $i;
            while ($i < $length && $braceCount > 0) {
                if ($text[$i] === '{') {
                    $braceCount++;
                } elseif ($text[$i] === '}') {
                    $braceCount--;
                }
                $i++;
            }
            if ($braceCount > 0) {
                $offset = $pos + 10;
                continue;
            }
            
            $pluralBlock = substr($text, $pos, $i - $pos);
            $inner = substr($text, $startInner, $i - 2 - $startInner);
            
            $varName = $variables[$varPlaceholderNum - 1] ?? 'count';
            
            $categories = self::parseMediaWikiCategories($inner, $locale, $varPlaceholderNum);
            $converted = self::buildIcuPlural($varName, $categories);
            
            $text = substr_replace($text, $converted, $pos, strlen($pluralBlock));
            $offset = $pos + strlen($converted);
        }
        
        // Convert positional placeholders ($1, $2, etc.) back to named placeholders ({limit}, {months}, or __date__)
        foreach ($variables as $index => $var) {
            $positional = '$' . ($index + 1);
            if (str_starts_with($var, '__') && str_ends_with($var, '__')) {
                $placeholder = $var;
            } else {
                $placeholder = '{' . $var . '}';
            }
            $text = str_replace($positional, $placeholder, $text);
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
    private static function buildMediaWikiPlural(array $categories, string $locale, int $varPlaceholderNum): string
    {
        $parts = [];
        $placeholder = '$' . $varPlaceholderNum;
        foreach ($categories as $cat => $text) {
            if (str_starts_with($cat, '=')) {
                $num = substr($cat, 1);
                $textWithPlaceholder = str_replace('#', $placeholder, $text);
                $parts[] = $num . '=' . $textWithPlaceholder;
            }
        }

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
            $parts[] = str_replace('#', $placeholder, $text);
        }

        return '{{PLURAL:' . $placeholder . '|' . implode('|', $parts) . '}}';
    }

    /**
     * Helper to parse MediaWiki categories.
     */
    private static function parseMediaWikiCategories(string $inner, string $locale, int $varPlaceholderNum): array
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
        
        $placeholder = '$' . $varPlaceholderNum;
        foreach ($categories as $cat => $text) {
            $categories[$cat] = str_replace($placeholder, '#', $text);
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
     * Finds the names of all placeholder variables (named placeholders, custom placeholders, and plural count vars) in the text.
     */
    public static function getPlaceholderVariables(string $text): array
    {
        $variables = [];
        $length = strlen($text);
        $i = 0;
        
        while ($i < $length) {
            if ($text[$i] === '{') {
                $start = $i;
                $braceCount = 1;
                $i++;
                $commaPos = false;
                
                while ($i < $length && $braceCount > 0) {
                    if ($text[$i] === '{') {
                        $braceCount++;
                    } elseif ($text[$i] === '}') {
                        $braceCount--;
                    } elseif ($text[$i] === ',' && $braceCount === 1 && $commaPos === false) {
                        $commaPos = $i;
                    }
                    $i++;
                }
                
                $block = substr($text, $start, $i - $start);
                
                if ($commaPos !== false) {
                    $beforeComma = substr($text, $start + 1, $commaPos - $start - 1);
                    $varName = trim($beforeComma);
                    
                    $afterComma = substr($text, $commaPos + 1);
                    $nextComma = strpos($afterComma, ',');
                    if ($nextComma !== false) {
                        $type = trim(substr($afterComma, 0, $nextComma));
                        if ($type === 'plural') {
                            if ($varName !== '' && !in_array($varName, $variables, true)) {
                                $variables[] = $varName;
                            }
                            
                            $secondCommaPos = $commaPos + 1 + $nextComma;
                            $inner = substr($text, $secondCommaPos + 1, $i - 1 - ($secondCommaPos + 1));
                            
                            $innerVars = self::getPlaceholderVariables($inner);
                            foreach ($innerVars as $v) {
                                if (!in_array($v, $variables, true)) {
                                    $variables[] = $v;
                                }
                            }
                        }
                    }
                } else {
                    $varName = trim(substr($block, 1, -1));
                    if (str_contains($varName, '{')) {
                        $innerVars = self::getPlaceholderVariables($varName);
                        foreach ($innerVars as $v) {
                            if (!in_array($v, $variables, true)) {
                                    $variables[] = $v;
                            }
                        }
                    } else {
                        if ($varName !== '' && !in_array($varName, $variables, true)) {
                            $variables[] = $varName;
                        }
                    }
                }
            } elseif ($text[$i] === '_' && $i + 1 < $length && $text[$i + 1] === '_') {
                if (preg_match('/^__([a-zA-Z0-9_-]+)__/', substr($text, $i), $matches)) {
                    $varName = $matches[0];
                    if (!in_array($varName, $variables, true)) {
                        $variables[] = $varName;
                    }
                    $i += strlen($varName);
                } else {
                    $i++;
                }
            } else {
                $i++;
            }
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
