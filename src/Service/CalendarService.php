<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Poll;
use App\Entity\Proposal;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CalendarService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array{
     *   isAllDay: bool,
     *   start: \DateTimeImmutable,
     *   end: \DateTimeImmutable,
     *   startUtc: \DateTimeImmutable,
     *   endUtc: \DateTimeImmutable
     * }
     */
    public function getEventDates(Poll $poll, Proposal $proposal): array
    {
        $date = $proposal->getDate();
        $tzName = $poll->getTimezoneName() ?: 'UTC';

        try {
            $tz = new \DateTimeZone($tzName);
        } catch (\Exception) {
            $tz = new \DateTimeZone('UTC');
        }

        if ($date && $date->getValue()) {
            $dateValue = $date->getValue();
            $dateStr = (new \DateTimeImmutable('@' . $dateValue->getTimestamp()))
                ->setTimezone($tz)
                ->format('Y-m-d');
        } else {
            $dateStr = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        }

        $startTime = $proposal->getStartTime();
        $endTime = $proposal->getEndTime();

        // If no explicit startTime on proposal, check if label has time (e.g. "14:00" or "14:00 - 16:00")
        if (!$startTime && $proposal->getLabel()) {
            if (preg_match('/(\d{1,2}:\d{2})/', $proposal->getLabel(), $matches)) {
                $startTime = $matches[1] . ':00';
            }
            if (preg_match('/-\s*(\d{1,2}:\d{2})/', $proposal->getLabel(), $matchesEnd)) {
                $endTime = $matchesEnd[1] . ':00';
            }
        }

        if (!$startTime) {
            // All-day event
            $start = new \DateTimeImmutable($dateStr . ' 00:00:00', $tz);
            $end = $start->modify('+1 day');
            return [
                'isAllDay' => true,
                'start' => $start,
                'end' => $end,
                'startUtc' => $start->setTimezone(new \DateTimeZone('UTC')),
                'endUtc' => $end->setTimezone(new \DateTimeZone('UTC')),
            ];
        }

        // Timed event
        $start = new \DateTimeImmutable($dateStr . ' ' . $startTime, $tz);
        if ($endTime) {
            $end = new \DateTimeImmutable($dateStr . ' ' . $endTime, $tz);
            if ($end <= $start) {
                $end = $end->modify('+1 day');
            }
        } else {
            $end = $start->modify('+1 hour');
        }

        return [
            'isAllDay' => false,
            'start' => $start,
            'end' => $end,
            'startUtc' => $start->setTimezone(new \DateTimeZone('UTC')),
            'endUtc' => $end->setTimezone(new \DateTimeZone('UTC')),
        ];
    }

    /**
     * @param Proposal[]|Proposal $proposals
     */
    public function generateIcs(Poll $poll, Proposal|array $proposals, ?string $pollUrl = null): string
    {
        if ($proposals instanceof Proposal) {
            $proposals = [$proposals];
        }

        if ($pollUrl === null) {
            $pollUrl = $this->urlGenerator->generate('poll', ['slug' => $poll->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $nowUtc = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Pollaris//Pollaris Event//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($proposals as $proposal) {
            $dates = $this->getEventDates($poll, $proposal);
            $uid = sprintf('pollaris-%d-%d@%s', $poll->getId() ?? 0, $proposal->getId() ?? 0, parse_url($pollUrl, PHP_URL_HOST) ?: 'pollaris');

            $summary = $poll->getTitle();
            if ($proposal->getLabel()) {
                $summary .= ' - ' . $proposal->getLabel();
            }

            $description = $poll->getDescription() ? strip_tags($poll->getDescription()) . "\n\n" : '';
            $description .= 'Pollaris: ' . $pollUrl;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . $nowUtc;

            if ($dates['isAllDay']) {
                $lines[] = 'DTSTART;VALUE=DATE:' . $dates['start']->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:' . $dates['end']->format('Ymd');
            } else {
                $lines[] = 'DTSTART:' . $dates['startUtc']->format('Ymd\THis\Z');
                $lines[] = 'DTEND:' . $dates['endUtc']->format('Ymd\THis\Z');
            }

            $lines[] = 'SUMMARY:' . $this->escapeIcsText($summary);
            $lines[] = 'DESCRIPTION:' . $this->escapeIcsText($description);
            $lines[] = 'URL:' . $pollUrl;
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return $this->formatIcsLines($lines);
    }

    public function getGoogleCalendarUrl(Poll $poll, Proposal $proposal, ?string $pollUrl = null): string
    {
        if ($pollUrl === null) {
            $pollUrl = $this->urlGenerator->generate('poll', ['slug' => $poll->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $dates = $this->getEventDates($poll, $proposal);
        $title = $poll->getTitle();
        if ($proposal->getLabel()) {
            $title .= ' - ' . $proposal->getLabel();
        }

        $details = $poll->getDescription() ? strip_tags($poll->getDescription()) . "\n\n" : '';
        $details .= 'Poll: ' . $pollUrl;

        if ($dates['isAllDay']) {
            $dateParam = $dates['start']->format('Ymd') . '/' . $dates['end']->format('Ymd');
        } else {
            $dateParam = $dates['startUtc']->format('Ymd\THis\Z') . '/' . $dates['endUtc']->format('Ymd\THis\Z');
        }

        $params = [
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => $dateParam,
            'details' => $details,
            'location' => $pollUrl,
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }

    public function getOutlookWebUrl(Poll $poll, Proposal $proposal, ?string $pollUrl = null): string
    {
        if ($pollUrl === null) {
            $pollUrl = $this->urlGenerator->generate('poll', ['slug' => $poll->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $dates = $this->getEventDates($poll, $proposal);
        $title = $poll->getTitle();
        if ($proposal->getLabel()) {
            $title .= ' - ' . $proposal->getLabel();
        }

        $details = $poll->getDescription() ? strip_tags($poll->getDescription()) . "\n\n" : '';
        $details .= 'Poll: ' . $pollUrl;

        $params = [
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => $title,
            'body' => $details,
            'location' => $pollUrl,
        ];

        if ($dates['isAllDay']) {
            $params['startdt'] = $dates['start']->format('Y-m-d');
            $params['enddt'] = $dates['end']->format('Y-m-d');
            $params['allday'] = 'true';
        } else {
            $params['startdt'] = $dates['startUtc']->format('Y-m-d\TH:i:s\Z');
            $params['enddt'] = $dates['endUtc']->format('Y-m-d\TH:i:s\Z');
        }

        return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($params);
    }

    public function getYahooCalendarUrl(Poll $poll, Proposal $proposal, ?string $pollUrl = null): string
    {
        if ($pollUrl === null) {
            $pollUrl = $this->urlGenerator->generate('poll', ['slug' => $poll->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $dates = $this->getEventDates($poll, $proposal);
        $title = $poll->getTitle();
        if ($proposal->getLabel()) {
            $title .= ' - ' . $proposal->getLabel();
        }

        $details = $poll->getDescription() ? strip_tags($poll->getDescription()) . "\n\n" : '';
        $details .= 'Poll: ' . $pollUrl;

        $params = [
            'v' => '60',
            'view' => 'd',
            'type' => '20',
            'title' => $title,
            'desc' => $details,
            'in_loc' => $pollUrl,
        ];

        if ($dates['isAllDay']) {
            $params['st'] = $dates['start']->format('Ymd');
            $params['dur'] = 'allday';
        } else {
            $params['st'] = $dates['startUtc']->format('Ymd\THis\Z');
            $params['et'] = $dates['endUtc']->format('Ymd\THis\Z');
        }

        return 'https://calendar.yahoo.com/?' . http_build_query($params);
    }

    public function getWebcalUrl(Poll $poll, ?Proposal $proposal = null): string
    {
        $params = ['slug' => $poll->getSlug()];
        $routeName = 'poll ics';
        if ($proposal !== null && $proposal->getId() !== null) {
            $params['proposalId'] = $proposal->getId();
            $routeName = 'poll proposal ics';
        }

        $url = $this->urlGenerator->generate($routeName, $params, UrlGeneratorInterface::ABSOLUTE_URL);
        return (string) preg_replace('/^https?:\/\//i', 'webcal://', $url);
    }

    private function escapeIcsText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        return str_replace(["\r\n", "\n", "\r"], '\n', $text);
    }

    /**
     * @param string[] $lines
     */
    private function formatIcsLines(array $lines): string
    {
        $output = '';
        foreach ($lines as $line) {
            while (strlen($line) > 75) {
                $output .= substr($line, 0, 75) . "\r\n ";
                $line = substr($line, 75);
            }
            $output .= $line . "\r\n";
        }
        return $output;
    }
}
