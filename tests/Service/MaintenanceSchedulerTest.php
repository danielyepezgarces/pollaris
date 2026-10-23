<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Service\MaintenanceScheduler;
use PHPUnit\Framework\TestCase;

class MaintenanceSchedulerTest extends TestCase
{
    private MaintenanceScheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new MaintenanceScheduler();
    }

    public function testEmptyOrNullConfigReturnsFalse(): void
    {
        $this->assertFalse($this->scheduler->isMaintenanceActive(null));
        $this->assertFalse($this->scheduler->isMaintenanceActive(''));
        $this->assertFalse($this->scheduler->isMaintenanceActive('   '));
    }

    public function testInvalidConfigReturnsFalse(): void
    {
        $this->assertFalse($this->scheduler->isMaintenanceActive('invalid schedule string'));
    }

    public function testOneShotMaintenance(): void
    {
        // Past maintenance
        $this->assertFalse($this->scheduler->isMaintenanceActive('2020-01-01 10:00 to 2020-01-01 11:00 UTC'));

        // Future maintenance
        $this->assertFalse($this->scheduler->isMaintenanceActive('2099-01-01 10:00 to 2099-01-01 11:00 UTC'));

        // Currently active maintenance
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $start = (clone $now)->modify('-10 minutes')->format('Y-m-d H:i');
        $end = (clone $now)->modify('+10 minutes')->format('Y-m-d H:i');
        $this->assertTrue($this->scheduler->isMaintenanceActive("$start to $end UTC"));
    }
}
