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

namespace App\Command\User;

use App\Repository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:user:promote',
    description: 'Grants ROLE_ADMIN to an existing user.',
)]
class PromoteCommand extends Command
{
    public function __construct(
        private Repository\UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'The username of the user to promote.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = trim($input->getArgument('username'));

        $user = $this->userRepository->findOneBy(['username' => $username]);

        if ($user === null) {
            $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errOutput->writeln("<error>User \"{$username}\" not found.</error>");
            return Command::FAILURE;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $output->writeln("<info>User \"{$username}\" already has ROLE_ADMIN.</info>");
            return Command::SUCCESS;
        }

        $roles[] = 'ROLE_ADMIN';
        $user->setRoles(array_unique($roles));
        $this->userRepository->save($user);

        $output->writeln("<info>User \"{$username}\" has been promoted to admin.</info>");
        return Command::SUCCESS;
    }
}
