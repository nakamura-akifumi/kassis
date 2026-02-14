<?php

namespace App\Service;

use App\Entity\CalendarHoliday;
use App\Repository\CalendarEventRepository;
use App\Repository\CalendarHolidayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Yasumi\Yasumi;

class CalendarService
{
    public function __construct(
        private CalendarHolidayRepository $holidayRepository,
        private CalendarEventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function isClosedDate(\DateTimeInterface $date, string $countryCode = 'JP'): bool
    {
        /*
        if ($this->holidayRepository->existsOnDate($date, $countryCode)) {
            return true;
        }
        */
        return $this->eventRepository->existsClosedOnDate($date);
    }

    public function adjustToNextOpenDate(\DateTimeInterface $date, bool $isAdjustDueOnClosedDay, string $countryCode = 'JP'): \DateTimeInterface
    {
        $cursor = \DateTimeImmutable::createFromInterface($date);

        while ($this->isClosedDate($cursor, $countryCode)) {
            if (!$isAdjustDueOnClosedDay) {
                $cursor = $cursor->modify('+1 day');
            } else {
                $cursor = $cursor->modify('-1 day');
            }
        }

        return $cursor;
    }

    public function syncJapaneseHolidays(int $year, string $locale = 'ja_JP'): int
    {
        $holidays = Yasumi::create('Japan', $year, $locale);
        $existing = $this->holidayRepository->findByYear($year, 'JP');
        $existingByDate = [];

        foreach ($existing as $holiday) {
            $dateKey = $holiday->getHolidayDate()?->format('Y-m-d');
            if ($dateKey !== null) {
                $existingByDate[$dateKey] = $holiday;
            }
        }

        $count = 0;
        foreach ($holidays as $holiday) {
            $date = \DateTimeImmutable::createFromInterface($holiday);
            $dateKey = $date->format('Y-m-d');

            $entity = $existingByDate[$dateKey] ?? new CalendarHoliday();
            $entity
                ->setHolidayDate($date)
                ->setName($holiday->getName())
                ->setCountryCode('JP')
                ->setSource('yasumi');

            if (!isset($existingByDate[$dateKey])) {
                $this->entityManager->persist($entity);
            }

            $count++;
        }

        $this->entityManager->flush();

        return $count;
    }
}
