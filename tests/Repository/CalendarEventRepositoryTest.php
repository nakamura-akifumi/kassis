<?php

namespace App\Tests\Repository;

use App\Entity\CalendarEvent;
use App\Repository\CalendarEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests CalendarEventRepository and the existsClosedOnDate method.
 */
class CalendarEventRepositoryTest extends KernelTestCase
{
    private ?CalendarEventRepository $repository = null;
    private ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->repository = $this->entityManager->getRepository(CalendarEvent::class);
        $this->initDatabase();
    }

    private function initDatabase(): void
    {
        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metaData);
        $schemaTool->createSchema($metaData);
    }

    private function createCalendarEvent(
        string $uid,
        \DateTimeInterface $dtStart,
        ?\DateTimeInterface $dtEnd = null,
        bool $isClosed = true,
        ?string $rrule = null,
        bool $allDay = false
    ): CalendarEvent {
        $event = new CalendarEvent();
        $event->setUid($uid);
        $event->setSummary('Test Event');
        $event->setDtStart($dtStart);
        $event->setDtEnd($dtEnd);
        $event->setIsClosed($isClosed);
        $event->setRrule($rrule);
        $event->setAllDay($allDay);
        $this->entityManager->persist($event);

        return $event;
    }

    public function testExistsClosedOnDateReturnsTrueForClosedNonRecurringEvent(): void
    {
        $start = new \DateTimeImmutable('2026-02-10 09:00:00', new \DateTimeZone('UTC'));
        $end = new \DateTimeImmutable('2026-02-10 17:00:00', new \DateTimeZone('UTC'));
        $this->createCalendarEvent('uid-closed-single', $start, $end);
        $this->entityManager->flush();

        $result = $this->repository->existsClosedOnDate(new \DateTimeImmutable('2026-02-10', new \DateTimeZone('UTC')));

        $this->assertTrue($result);
    }

    public function testExistsClosedOnDateReturnsFalseForClosedNonRecurringEventOutsideRange(): void
    {
        $start = new \DateTimeImmutable('2026-02-12 09:00:00', new \DateTimeZone('UTC'));
        $end = new \DateTimeImmutable('2026-02-12 17:00:00', new \DateTimeZone('UTC'));
        $this->createCalendarEvent('uid-closed-outside', $start, $end);
        $this->entityManager->flush();

        $result = $this->repository->existsClosedOnDate(new \DateTimeImmutable('2026-02-10', new \DateTimeZone('UTC')));

        $this->assertFalse($result);
    }

    public function testExistsClosedOnDateReturnsFalseWhenOnlyOpenEventsExist(): void
    {
        $start = new \DateTimeImmutable('2026-02-10 09:00:00', new \DateTimeZone('UTC'));
        $end = new \DateTimeImmutable('2026-02-10 17:00:00', new \DateTimeZone('UTC'));
        $this->createCalendarEvent('uid-open-single', $start, $end, false);
        $this->entityManager->flush();

        $result = $this->repository->existsClosedOnDate(new \DateTimeImmutable('2026-02-10', new \DateTimeZone('UTC')));

        $this->assertFalse($result);
    }

    public function testExistsClosedOnDateReturnsTrueForRecurringEventOccurrence(): void
    {
        $start = new \DateTimeImmutable('2026-02-01 09:00:00', new \DateTimeZone('UTC'));
        $rrule = 'FREQ=DAILY;COUNT=5';
        $this->createCalendarEvent('uid-closed-rrule', $start, null, true, $rrule);
        $this->entityManager->flush();

        $result = $this->repository->existsClosedOnDate(new \DateTimeImmutable('2026-02-03', new \DateTimeZone('UTC')));

        $this->assertTrue($result);
    }

    public function testExistsClosedOnDateReturnsFalseWhenRecurringEventDoesNotOccurOnDate(): void
    {
        $start = new \DateTimeImmutable('2026-02-01 09:00:00', new \DateTimeZone('UTC'));
        $rrule = 'FREQ=DAILY;COUNT=3';
        $this->createCalendarEvent('uid-closed-rrule-miss', $start, null, true, $rrule);
        $this->entityManager->flush();

        $result = $this->repository->existsClosedOnDate(new \DateTimeImmutable('2026-02-10', new \DateTimeZone('UTC')));

        $this->assertFalse($result);
    }

    public function testExistsClosedOnDateReturnsTrueForWeeklyRecurringClosedDay(): void
    {
        $start = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC')); // Thursday
        $rrule = 'FREQ=WEEKLY;BYDAY=TH;UNTIL=20261231T235959Z';
        $this->createCalendarEvent('uid-closed-weekly', $start, null, true, $rrule, true);
        $this->entityManager->flush();

        $result = $this->repository->existsClosedOnDate(new \DateTimeImmutable('2026-02-12', new \DateTimeZone('UTC')));

        $this->assertTrue($result);
    }

    public function testExistsClosedOnDateReturnsFalseForWeeklyRecurringClosedDayOnOtherWeekday(): void
    {
        $start = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC')); // Thursday
        $rrule = 'FREQ=WEEKLY;BYDAY=TH;UNTIL=20261231T235959Z';
        $this->createCalendarEvent('uid-closed-weekly-miss', $start, null, true, $rrule, true);
        $this->entityManager->flush();

        $result = $this->repository->existsClosedOnDate(new \DateTimeImmutable('2026-02-11', new \DateTimeZone('UTC')));
        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
        $this->repository = null;
    }
}
