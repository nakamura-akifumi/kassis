<?php

namespace App\Repository;

use App\Entity\CalendarEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Recur\EventIterator;

/**
 * @extends ServiceEntityRepository<CalendarEvent>
 */
class CalendarEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarEvent::class);
    }

    public function existsClosedOnDate(\DateTimeInterface $date): bool
    {
        $start = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $end = \DateTimeImmutable::createFromInterface($date)->setTime(23, 59, 59);

        $count = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.is_closed = true')
            ->andWhere('e.rrule IS NULL')
            ->andWhere('e.dt_start <= :end')
            ->andWhere('(e.dt_end IS NULL OR e.dt_end >= :start)')
            ->setParameter('start', $start, Types::DATETIME_IMMUTABLE)
            ->setParameter('end', $end, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();

        if (((int) $count) > 0) {
            return true;
        }

        $events = $this->createQueryBuilder('e')
            ->andWhere('e.is_closed = true')
            ->andWhere('e.rrule IS NOT NULL')
            ->andWhere('e.dt_start IS NOT NULL')
            ->andWhere('e.dt_start <= :end')
            ->setParameter('end', $end, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();

        foreach ($events as $event) {
            if ($this->occursOnDate($event, $start, $end)) {
                return true;
            }
        }

        return false;
    }

    private function occursOnDate(CalendarEvent $event, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        $dtStart = $event->getDtStart();
        if ($dtStart === null) {
            return false;
        }

        $calendar = new VCalendar();
        $vevent = $calendar->add('VEVENT');

        $vevent->add('UID', $event->getUid());

        if ($event->isAllDay()) {
            $vevent->add('DTSTART', \DateTimeImmutable::createFromInterface($dtStart), ['VALUE' => 'DATE']);
            $dtEnd = $event->getDtEnd();
            if ($dtEnd !== null) {
                $vevent->add('DTEND', \DateTimeImmutable::createFromInterface($dtEnd), ['VALUE' => 'DATE']);
            }
        } else {
            $vevent->add('DTSTART', $dtStart);
            $dtEnd = $event->getDtEnd();
            if ($dtEnd !== null) {
                $vevent->add('DTEND', $dtEnd);
            }
        }

        if ($event->getTimezone()) {
            $vevent->DTSTART['TZID'] = $event->getTimezone();
            if (isset($vevent->DTEND)) {
                $vevent->DTEND['TZID'] = $event->getTimezone();
            }
        }

        $rrule = $event->getRrule();
        if ($rrule === null || $rrule === '') {
            return false;
        }
        $vevent->add('RRULE', $rrule);

        if ($event->getRdate()) {
            $vevent->add('RDATE', $event->getRdate());
        }
        if ($event->getExdate()) {
            $vevent->add('EXDATE', $event->getExdate());
        }

        // Use VEVENT directly to avoid UID lookup mismatches in VCALENDAR.
        $iterator = new EventIterator($vevent);
        $iterator->fastForward($start);

        if (!$iterator->valid()) {
            return false;
        }

        $occurrence = $iterator->getDTStart();
        return $occurrence <= $end;
    }
}
