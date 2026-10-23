<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

class MaintenanceScheduler
{
    /**
     * Checks if maintenance is active based on the schedule configuration.
     * Supported formats:
     * - Daily: "daily 23:00 to 23:30 America/Bogota"
     * - Weekly: "weekly Wednesday 23:00 to 23:30 America/Bogota"
     * - One-shot: "2026-07-08 23:00 to 2026-07-08 23:30 America/Bogota"
     */
    public function isMaintenanceActive(?string $scheduleConfig): bool
    {
        if (!$scheduleConfig || trim($scheduleConfig) === '') {
            return false;
        }

        $scheduleConfig = trim($scheduleConfig);
        $parts = explode(' to ', $scheduleConfig);
        if (count($parts) !== 2) {
            return false;
        }

        $startPart = trim($parts[0]);
        $endPart = trim($parts[1]);

        // Find timezone at the end of the endPart
        $endTokens = explode(' ', $endPart);
        $timezoneStr = 'UTC';
        if (count($endTokens) > 1) {
            $timezoneStr = array_pop($endTokens);
            $endPart = implode(' ', $endTokens);
        }

        try {
            $tz = new \DateTimeZone($timezoneStr);
        } catch (\Exception) {
            $tz = new \DateTimeZone('UTC');
        }

        $now = new \DateTime('now', $tz);

        // Daily schedule
        if (str_starts_with($startPart, 'daily ')) {
            $startTimeStr = substr($startPart, 6);
            $endTimeStr = $endPart;

            try {
                $startToday = new \DateTime($now->format('Y-m-d ') . $startTimeStr, $tz);
                $endToday = new \DateTime($now->format('Y-m-d ') . $endTimeStr, $tz);
            } catch (\Exception) {
                return false;
            }

            // Handle wrap-around (e.g. 23:00 to 01:00 next day)
            if ($endToday < $startToday) {
                if ($now < $endToday) {
                    $startToday->modify('-1 day');
                } else {
                    $endToday->modify('+1 day');
                }
            }

            return $now >= $startToday && $now <= $endToday;
        }

        // Weekly schedule
        if (str_starts_with($startPart, 'weekly ')) {
            $weeklyTokens = explode(' ', substr($startPart, 7));
            if (count($weeklyTokens) !== 2) {
                return false;
            }
            $dayName = $weeklyTokens[0];
            $startTimeStr = $weeklyTokens[1];
            $endTimeStr = $endPart;

            $currentDayName = $now->format('l');

            try {
                $startDay = new \DateTime($now->format('Y-m-d ') . $startTimeStr, $tz);
                $endDay = new \DateTime($now->format('Y-m-d ') . $endTimeStr, $tz);
            } catch (\Exception) {
                return false;
            }

            if ($endDay < $startDay) {
                $endDay->modify('+1 day');
            }

            // Check if today matches target dayName
            if (strcasecmp($currentDayName, $dayName) === 0) {
                if ($now >= $startDay && $now <= $endDay) {
                    return true;
                }
            }

            // Check if yesterday matched target dayName (for wrap-around windows)
            $yesterday = clone $now;
            $yesterday->modify('-1 day');
            if (strcasecmp($yesterday->format('l'), $dayName) === 0) {
                $startDay->modify('-1 day');
                $endDay->modify('-1 day');
                if ($now >= $startDay && $now <= $endDay) {
                    return true;
                }
            }

            return false;
        }

        // Specific datetime range
        try {
            $startDate = new \DateTime($startPart, $tz);
            $endDate = new \DateTime($endPart, $tz);
            return $now >= $startDate && $now <= $endDate;
        } catch (\Exception) {
            return false;
        }
    }
}
