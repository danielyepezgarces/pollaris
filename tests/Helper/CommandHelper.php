<?php

// This file is part of Pollaris.
// Copyright 2022-2025 Probesys (Bileto)
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Helper;

use PHPUnit\Framework\Attributes\BeforeClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

trait CommandHelper
{
    /**
     * @var \Symfony\Bundle\FrameworkBundle\Console\Application
     */
    protected static $application;

    #[BeforeClass]
    public static function setUpConsoleTestsHelper(): void
    {
        self::bootKernel();
        assert(self::$kernel !== null);
        self::$application = new Application(self::$kernel);
    }

    /**
     * @param array<string, mixed> $args
     * @param list<string> $inputs
     */
    protected static function executeCommand(
        string $command,
        array $args = [],
        array $inputs = [],
    ): CommandTester {
        $command = self::$application->find($command);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs($inputs);
        $commandTester->execute($args, [
            'interactive' => !empty($inputs),
            'capture_stderr_separately' => true,
        ]);
        return $commandTester;
    }
}
