<?php

// This file is part of Pollaris.
// Copyright 2026 Adrien Scholaert <adrien@framasoft.org>
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Modified by Daniel Yepez Garces on 2026-04-15:
// - Migrated database backend from PostgreSQL to MariaDB for Toolforge deployment
// - Added Wikimedia login support
// - Removed local username/password authentication
// - Added multilingual survey support
// - Added user timezone display for survey times when different from server UTC

namespace App\Tests\Command\Poll;

use App\Tests\Helper;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CleanupCommandTest extends KernelTestCase
{
    use Factories;
    use Helper\CommandHelper;
    use ResetDatabase;

    public function testExpiredCompletedPollsAreDeleted(): void
    {
        // Completed poll closed more than 6 months ago (expired)
        $expired = Factory\PollFactory::new()
            ->completed()
            ->create([
                'closedAt' => new \DateTimeImmutable('-7 months')
            ]);

        // Completed poll but recently closed (valid)
        $valid = Factory\PollFactory::new()
            ->completed()
            ->create([
                'closedAt' => new \DateTimeImmutable('-1 month')
            ]);

        $this->assertSame(2, Factory\PollFactory::count());

        $tester = self::executeCommand('app:poll:cleanup');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        Factory\PollFactory::assert()->notExists(['id' => $expired->getId()]);
        Factory\PollFactory::assert()->exists(['id' => $valid->getId()]);
        Factory\PollFactory::assert()->count(1);
    }

    public function testExpiredIncompletePollsAreDeleted(): void
    {
        // Incomplete poll closed more than 2 months ago (expired)
        $expired = Factory\PollFactory::new()
            ->create([
                'completedAt' => null,
                'closedAt' => new \DateTimeImmutable('-3 months')
            ]);

        // Incomplete poll but recently closed (valid)
        $valid = Factory\PollFactory::new()
            ->create([
                'completedAt' => null,
                'closedAt' => new \DateTimeImmutable('-1 month')
            ]);

        $this->assertSame(2, Factory\PollFactory::count());

        $tester = self::executeCommand('app:poll:cleanup');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        Factory\PollFactory::assert()->notExists(['id' => $expired->getId()]);
        Factory\PollFactory::assert()->exists(['id' => $valid->getId()]);
        Factory\PollFactory::assert()->count(1);
    }

    public function testWithNoExpiredPolls(): void
    {
        Factory\PollFactory::createMany(2, [
            'completedAt' => new \DateTimeImmutable(),
            'closedAt' => new \DateTimeImmutable('-1 month'),
        ]);

        $tester = self::executeCommand('app:poll:cleanup');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        Factory\PollFactory::assert()->count(2);

        $this->assertStringContainsString(
            'No expired polls',
            $tester->getDisplay()
        );
    }

    public function testDryRunDoesNotDeleteExpiredPolls(): void
    {
        $expiredCompleted = Factory\PollFactory::new()
            ->completed()
            ->create([
                'closedAt' => new \DateTimeImmutable('-7 months')
            ]);

        $expiredIncomplete = Factory\PollFactory::new()
            ->create([
                'completedAt' => null,
                'closedAt' => new \DateTimeImmutable('-3 months')
            ]);

        $this->assertSame(2, Factory\PollFactory::count());

        $tester = self::executeCommand('app:poll:cleanup', [
            '--dry-run' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        Factory\PollFactory::assert()->count(2);

        $this->assertStringContainsString(
            'would be deleted',
            $tester->getDisplay()
        );
    }

    public function testDryRunWithNoExpiredPolls(): void
    {
        Factory\PollFactory::createMany(2, [
            'completedAt' => new \DateTimeImmutable(),
            'closedAt' => new \DateTimeImmutable('-1 month'),
        ]);

        $tester = self::executeCommand('app:poll:cleanup', [
            '--dry-run' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        Factory\PollFactory::assert()->count(2);

        $this->assertStringContainsString(
            'No expired polls',
            $tester->getDisplay()
        );
    }
}
