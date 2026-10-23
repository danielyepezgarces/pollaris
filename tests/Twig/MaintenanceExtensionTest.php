<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Twig;

use App\Service\MaintenanceScheduler;
use App\Twig\MaintenanceExtension;
use PHPUnit\Framework\TestCase;

class MaintenanceExtensionTest extends TestCase
{
    public function testGetFunctions(): void
    {
        $scheduler = $this->createMock(MaintenanceScheduler::class);
        $extension = new MaintenanceExtension($scheduler);

        $functions = $extension->getFunctions();
        $this->assertCount(1, $functions);
        $this->assertSame('is_maintenance_read_only', $functions[0]->getName());
    }

    public function testIsMaintenanceReadOnlyWhenConfigTrue(): void
    {
        $scheduler = $this->createMock(MaintenanceScheduler::class);
        $scheduler->expects($this->never())->method('isMaintenanceActive');

        $extension = new MaintenanceExtension($scheduler, 'true');
        $this->assertTrue($extension->isMaintenanceReadOnly());
    }

    public function testIsMaintenanceReadOnlyWhenSchedulerActive(): void
    {
        $scheduler = $this->createMock(MaintenanceScheduler::class);
        $scheduler->expects($this->once())
            ->method('isMaintenanceActive')
            ->with('daily 01:00 to 02:00 UTC')
            ->willReturn(true);

        $extension = new MaintenanceExtension($scheduler, 'false', 'daily 01:00 to 02:00 UTC');
        $this->assertTrue($extension->isMaintenanceReadOnly());
    }

    public function testIsMaintenanceReadOnlyWhenBothFalse(): void
    {
        $scheduler = $this->createMock(MaintenanceScheduler::class);
        $scheduler->expects($this->once())
            ->method('isMaintenanceActive')
            ->with(null)
            ->willReturn(false);

        $extension = new MaintenanceExtension($scheduler, 'false', null);
        $this->assertFalse($extension->isMaintenanceReadOnly());
    }
}
