<?php

namespace App\Tests\Service;

use App\Entity\Date;
use App\Entity\Poll;
use App\Entity\Proposal;
use App\Service\CalendarService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CalendarServiceTest extends TestCase
{
    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $mock = $this->createMock(UrlGeneratorInterface::class);
        $mock->method('generate')->willReturnCallback(function (string $name, array $params = []): string {
            $slug = $params['slug'] ?? 'test-poll';
            if ($name === 'poll ics') {
                return 'https://pollaris.toolforge.org/polls/' . $slug . '/calendar.ics';
            }
            if ($name === 'poll proposal ics') {
                return 'https://pollaris.toolforge.org/polls/' . $slug . '/proposals/' . ($params['proposalId'] ?? 1) . '/calendar.ics';
            }
            return 'https://pollaris.toolforge.org/polls/' . $slug;
        });
        return $mock;
    }

    public function testGetEventDatesAllDay(): void
    {
        $service = new CalendarService($this->createUrlGenerator());

        $poll = new Poll();
        $poll->setTimezoneName('America/Bogota');

        $date = new Date();
        $date->setValue(new \DateTimeImmutable('2026-12-15 00:00:00', new \DateTimeZone('America/Bogota')));

        $proposal = new Proposal();
        $proposal->setDate($date);
        $proposal->setLabel('Día completo');

        $dates = $service->getEventDates($poll, $proposal);

        $this->assertTrue($dates['isAllDay']);
        $this->assertSame('2026-12-15', $dates['start']->format('Y-m-d'));
        $this->assertSame('2026-12-16', $dates['end']->format('Y-m-d'));
    }

    public function testGetEventDatesWithTimes(): void
    {
        $service = new CalendarService($this->createUrlGenerator());

        $poll = new Poll();
        $poll->setTimezoneName('America/Bogota');

        $date = new Date();
        $date->setValue(new \DateTimeImmutable('2026-12-15 00:00:00', new \DateTimeZone('America/Bogota')));

        $proposal = new Proposal();
        $proposal->setDate($date);
        $proposal->setStartTime('14:30');
        $proposal->setEndTime('16:00');

        $dates = $service->getEventDates($poll, $proposal);

        $this->assertFalse($dates['isAllDay']);
        $this->assertSame('2026-12-15 14:30:00', $dates['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-15 16:00:00', $dates['end']->format('Y-m-d H:i:s'));
        // America/Bogota is UTC-5, so 14:30 Bogota is 19:30 UTC
        $this->assertSame('2026-12-15 19:30:00', $dates['startUtc']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-15 21:00:00', $dates['endUtc']->format('Y-m-d H:i:s'));
    }

    public function testGenerateIcs(): void
    {
        $service = new CalendarService($this->createUrlGenerator());

        $poll = new Poll();
        $poll->setTitle('Reunión de Wikimedia');
        $poll->setDescription('Discusión sobre nuevas propuestas.');
        $poll->setSlug('reunion-wikimedia');
        $poll->setTimezoneName('America/Bogota');

        $date = new Date();
        $date->setValue(new \DateTimeImmutable('2026-12-15 00:00:00', new \DateTimeZone('America/Bogota')));

        $proposal = new Proposal();
        $proposal->setDate($date);
        $proposal->setStartTime('10:00');
        $proposal->setEndTime('12:00');
        $proposal->setLabel('10:00 - 12:00');

        $ics = $service->generateIcs($poll, $proposal);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('VERSION:2.0', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('SUMMARY:Reunión de Wikimedia - 10:00 - 12:00', $ics);
        $this->assertStringContainsString('DTSTART:20261215T150000Z', $ics);
        $this->assertStringContainsString('DTEND:20261215T170000Z', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
    }

    public function testGetGoogleCalendarUrl(): void
    {
        $service = new CalendarService($this->createUrlGenerator());

        $poll = new Poll();
        $poll->setTitle('Asamblea 2026');
        $poll->setSlug('asamblea-2026');
        $poll->setTimezoneName('UTC');

        $date = new Date();
        $date->setValue(new \DateTimeImmutable('2026-11-25 00:00:00', new \DateTimeZone('UTC')));

        $proposal = new Proposal();
        $proposal->setDate($date);
        $proposal->setStartTime('15:00');
        $proposal->setEndTime('17:00');

        $url = $service->getGoogleCalendarUrl($poll, $proposal);

        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);
        $this->assertStringContainsString('action=TEMPLATE', $url);
        $this->assertStringContainsString('dates=20261125T150000Z%2F20261125T170000Z', $url);
        $this->assertStringContainsString('text=Asamblea+2026', $url);
    }

    public function testGetOutlookAndYahooAndWebcalUrls(): void
    {
        $service = new CalendarService($this->createUrlGenerator());

        $poll = new Poll();
        $poll->setTitle('Asamblea 2026');
        $poll->setSlug('asamblea-2026');
        $poll->setTimezoneName('UTC');

        $date = new Date();
        $date->setValue(new \DateTimeImmutable('2026-11-25 00:00:00', new \DateTimeZone('UTC')));

        $proposal = new Proposal();
        $proposal->setDate($date);
        $proposal->setStartTime('15:00');

        $outlookUrl = $service->getOutlookWebUrl($poll, $proposal);
        $this->assertStringStartsWith('https://outlook.live.com/calendar/0/deeplink/compose?', $outlookUrl);
        $this->assertStringContainsString('rru=addevent', $outlookUrl);

        $yahooUrl = $service->getYahooCalendarUrl($poll, $proposal);
        $this->assertStringStartsWith('https://calendar.yahoo.com/?', $yahooUrl);

        $webcalUrl = $service->getWebcalUrl($poll, $proposal);
        $this->assertStringStartsWith('webcal://', $webcalUrl);
    }
}
