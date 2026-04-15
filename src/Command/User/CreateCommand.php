<?php

// This file is part of Pollaris.
// Copyright 2022-2025 Probesys (Bileto)
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

use App\Entity;
use App\Repository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Creates a new user.',
)]
class CreateCommand extends Command
{
    public function __construct(
        private Repository\UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('username', '', InputOption::VALUE_OPTIONAL, 'The username of the user.');
        $this->addOption('password', '', InputOption::VALUE_OPTIONAL, 'The password of the user.');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper */
        $helper = $this->getHelper('question');

        $username = $input->getOption('username');
        if (!$username) {
            $question = new Question('Username: ');
            $username = $helper->ask($input, $output, $question);
            $input->setOption('username', $username);
        }

        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Password (hidden): ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);

            $password = $helper->ask($input, $output, $question);
            $input->setOption('password', $password);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = trim($input->getOption('username'));
        $password = $input->getOption('password');

        $user = new Entity\User();
        $user->setUsername($username);
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $output = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            foreach ($errors as $error) {
                $output->writeln($error->getMessage());
            }

            return Command::INVALID;
        }

        $this->userRepository->save($user);

        $output->writeln("The user \"{$user->getUsername()}\" has been created.");

        return Command::SUCCESS;
    }
}
