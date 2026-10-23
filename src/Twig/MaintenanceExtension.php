<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Service\MaintenanceScheduler;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MaintenanceExtension extends AbstractExtension
{
    public function __construct(
        private readonly MaintenanceScheduler $scheduler,
        private readonly string $maintenanceReadOnly = 'false',
        private readonly ?string $maintenanceSchedule = null
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_maintenance_read_only', [$this, 'isMaintenanceReadOnly']),
        ];
    }

    public function isMaintenanceReadOnly(): bool
    {
        return ($this->maintenanceReadOnly === 'true')
            || $this->scheduler->isMaintenanceActive($this->maintenanceSchedule);
    }
}
