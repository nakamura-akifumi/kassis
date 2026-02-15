<?php

namespace App\Tests\Service;

use App\Repository\CalendarEventRepository;
use App\Repository\CalendarHolidayRepository;
use App\Service\CalendarService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CalendarServiceTest extends TestCase
{
    public function testIsClosedDateDelegatesToEventRepository(): void
    {
        $holidayRepository = $this->createMock(CalendarHolidayRepository::class);
        $eventRepository = $this->createMock(CalendarEventRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $date = new \DateTimeImmutable('2026-02-01', new \DateTimeZone('UTC'));
        $eventRepository->expects($this->once())
            ->method('existsClosedOnDate')
            ->with($date)
            ->willReturn(true);

        $service = new CalendarService($holidayRepository, $eventRepository, $entityManager);

        $this->assertTrue($service->isClosedDate($date));
    }

    public function testAdjustToNextOpenDateMovesForwardWhenConfigured(): void
    {
        $holidayRepository = $this->createMock(CalendarHolidayRepository::class);
        $eventRepository = $this->createMock(CalendarEventRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $eventRepository->method('existsClosedOnDate')
            ->willReturnCallback(function (\DateTimeInterface $date): bool {
                return in_array($date->format('Y-m-d'), ['2026-02-01', '2026-02-02'], true);
            });

        $service = new CalendarService($holidayRepository, $eventRepository, $entityManager);

        $start = new \DateTimeImmutable('2026-02-01', new \DateTimeZone('UTC'));
        $result = $service->adjustToNextOpenDate($start, false);

        $this->assertSame('2026-02-03', $result->format('Y-m-d'));
    }

    public function testAdjustToNextOpenDateMovesBackwardWhenConfigured(): void
    {
        $holidayRepository = $this->createMock(CalendarHolidayRepository::class);
        $eventRepository = $this->createMock(CalendarEventRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $eventRepository->method('existsClosedOnDate')
            ->willReturnCallback(function (\DateTimeInterface $date): bool {
                return in_array($date->format('Y-m-d'), ['2026-02-03', '2026-02-02'], true);
            });

        $service = new CalendarService($holidayRepository, $eventRepository, $entityManager);

        $start = new \DateTimeImmutable('2026-02-03', new \DateTimeZone('UTC'));
        $result = $service->adjustToNextOpenDate($start, true);

        $this->assertSame('2026-02-01', $result->format('Y-m-d'));
    }
}
