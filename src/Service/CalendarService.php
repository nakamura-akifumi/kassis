<?php

namespace App\Service;

use App\Repository\CalendarEventRepository;

class CalendarService
{
    public function __construct(
        private CalendarEventRepository $eventRepository,
    ) {
    }

    public function isClosedDate(\DateTimeInterface $date, string $countryCode = 'JP'): bool
    {
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
}
