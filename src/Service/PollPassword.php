<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class PollPassword
{
    public function __construct(
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    public function hash(
        #[\SensitiveParameter]
        string $plainPassword
    ): string {
        return $this->getPasswordHasher()->hash($plainPassword);
    }

    public function verify(
        string $hashedPassword,
        #[\SensitiveParameter]
        string $plainPassword
    ): bool {
        return $this->getPasswordHasher()->verify($hashedPassword, $plainPassword);
    }

    private function getPasswordHasher(): PasswordHasherInterface
    {
        return $this->passwordHasherFactory->getPasswordHasher('common');
    }
}
