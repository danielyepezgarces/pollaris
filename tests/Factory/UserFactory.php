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

namespace App\Tests\Factory;

use App\Entity;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Entity\User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'username' => self::faker()->unique()->userName(),
            'password' => self::faker()->text(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Entity\User $user): void {
            $plainPassword = $user->getPassword();
            assert($plainPassword !== null);
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        });
    }

    public static function class(): string
    {
        return Entity\User::class;
    }
}
