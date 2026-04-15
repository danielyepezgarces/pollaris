<?php

// This file is part of Pollaris.
// Copyright 2022-2024 Probesys (Bileto)
// Copyright 2024-2023 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Command\User;

use App\Tests\Helper;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CreateCommandTest extends KernelTestCase
{
    use Factories;
    use Helper\CommandHelper;
    use ResetDatabase;

    public function testExecuteCreatesAUser(): void
    {
        /** @var UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $username = 'admin';
        $password = 'secret';

        $this->assertSame(0, Factory\UserFactory::count());

        $tester = self::executeCommand('app:user:create', [], [
            $username,
            $password,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(
            "The user \"{$username}\" has been created.\n",
            $tester->getDisplay()
        );
        $user = Factory\UserFactory::first();
        $this->assertSame($username, $user->getUsername());
        $this->assertTrue($passwordHasher->isPasswordValid($user, $password));
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testExecuteWorksWhenPassingOptions(): void
    {
        /** @var UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $username = 'admin';
        $password = 'secret';

        $this->assertSame(0, Factory\UserFactory::count());

        $tester = self::executeCommand('app:user:create', [
            '--username' => $username,
            '--password' => $password,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(
            "The user \"{$username}\" has been created.\n",
            $tester->getDisplay()
        );
        $user = Factory\UserFactory::first();
        $this->assertSame($username, $user->getUsername());
        $this->assertTrue($passwordHasher->isPasswordValid($user, $password));
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testExecuteFailsIfUsernameIsEmpty(): void
    {
        $username = '';
        $password = 'secret';

        $this->assertSame(0, Factory\UserFactory::count());

        $tester = self::executeCommand('app:user:create', [
            '--username' => $username,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(
            "Enter a username.\n",
            $tester->getErrorOutput()
        );
        $this->assertSame(0, Factory\UserFactory::count());
    }

    public function testExecuteFailsIfUsernameExists(): void
    {
        $username = 'admin';
        $password = 'secret';
        Factory\UserFactory::createOne([
            'username' => $username,
        ]);

        $this->assertSame(1, Factory\UserFactory::count());

        $tester = self::executeCommand('app:user:create', [
            '--username' => $username,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(
            "Enter a different username, this one is already in use.\n",
            $tester->getErrorOutput()
        );
        $this->assertSame(1, Factory\UserFactory::count());
    }
}
