<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class PollPassword
{
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
        $passwordHasherFactory = new PasswordHasherFactory([
            'common' => ['algorithm' => 'auto'],
        ]);
        return $passwordHasherFactory->getPasswordHasher('common');
    }
}
