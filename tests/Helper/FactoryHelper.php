<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Helper;

use App\Repository;
use Zenstruck\Foundry;

trait FactoryHelper
{
    /**
     * @template T of object
     *
     * @param T $entity
     */
    public function refresh(object $entity): void
    {
        $repositoryDecorator = Foundry\Persistence\repository($entity::class);
        /** @var Repository\BaseRepository<T> */
        $repository = $repositoryDecorator->inner();
        $repository->refresh($entity);
    }
}
