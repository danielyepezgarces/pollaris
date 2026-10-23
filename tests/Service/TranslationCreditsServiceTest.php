<?php

namespace App\Tests\Service;

use App\Service\TranslationCreditsService;
use PHPUnit\Framework\TestCase;

class TranslationCreditsServiceTest extends TestCase
{
    public function testGetTranslatorsReturnsUniqueAuthors(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $service = new TranslationCreditsService($projectDir);

        $translators = $service->getTranslators();

        $this->assertNotEmpty($translators);
        $this->assertGreaterThanOrEqual(15, count($translators));

        $usernames = array_column($translators, 'username');
        $this->assertContains('JorgeElias2302', $usernames);
        $this->assertContains('Toliño', $usernames);
        $this->assertContains('Siebrand', $usernames);

        // Check deduplication
        $uniqueUsernames = array_unique(array_map('mb_strtolower', $usernames));
        $this->assertCount(count($translators), $uniqueUsernames);

        // Check profile URL
        foreach ($translators as $translator) {
            $this->assertStringStartsWith('https://translatewiki.net/wiki/User:', $translator['profileUrl']);
            $this->assertNotEmpty($translator['languages']);
            foreach ($translator['languages'] as $lang) {
                $this->assertArrayHasKey('code', $lang);
                $this->assertArrayHasKey('name', $lang);
            }
        }

        $stats = $service->getStats();
        $this->assertSame(count($translators), $stats['translatorsCount']);
        $this->assertGreaterThanOrEqual(10, $stats['languagesCount']);
    }
}
