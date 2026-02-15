<?php

namespace App\Tests\Service;

use App\Repository\CalendarEventRepository;
use App\Service\CalendarService;
use PHPUnit\Framework\TestCase;

class CalendarServiceTest extends TestCase
{
    public function testIsClosedDateDelegatesToEventRepository(): void
    {
        $eventRepository = $this->createMock(CalendarEventRepository::class);

        $date = new \DateTimeImmutable('2026-02-01', new \DateTimeZone('UTC'));
        $eventRepository->expects($this->once())
            ->method('existsClosedOnDate')
            ->with($date)
            ->willReturn(true);

        $service = new CalendarService($eventRepository);

        $this->assertTrue($service->isClosedDate($date));
    }

    public function testAdjustToNextOpenDateMovesForwardWhenConfigured(): void
    {
        $eventRepository = $this->createMock(CalendarEventRepository::class);

        $eventRepository->method('existsClosedOnDate')
            ->willReturnCallback(function (\DateTimeInterface $date): bool {
                return in_array($date->format('Y-m-d'), ['2026-02-01', '2026-02-02'], true);
            });

        $service = new CalendarService($eventRepository);

        $start = new \DateTimeImmutable('2026-02-01', new \DateTimeZone('UTC'));
        $result = $service->adjustToNextOpenDate($start, false);

        $this->assertSame('2026-02-03', $result->format('Y-m-d'));
    }

    public function testAdjustToNextOpenDateMovesBackwardWhenConfigured(): void
    {
        $eventRepository = $this->createMock(CalendarEventRepository::class);

        $eventRepository->method('existsClosedOnDate')
            ->willReturnCallback(function (\DateTimeInterface $date): bool {
                return in_array($date->format('Y-m-d'), ['2026-02-03', '2026-02-02'], true);
            });

        $service = new CalendarService($eventRepository);

        $start = new \DateTimeImmutable('2026-02-03', new \DateTimeZone('UTC'));
        $result = $service->adjustToNextOpenDate($start, true);

        $this->assertSame('2026-02-01', $result->format('Y-m-d'));
    }
}
