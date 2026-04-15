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
