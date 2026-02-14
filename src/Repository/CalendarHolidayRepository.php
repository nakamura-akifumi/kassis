<?php

namespace App\Repository;

use App\Entity\CalendarHoliday;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CalendarHoliday>
 */
class CalendarHolidayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarHoliday::class);
    }

    /**
     * @return CalendarHoliday[]
     */
    public function findByYear(int $year, string $countryCode = 'JP'): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        return $this->createQueryBuilder('h')
            ->andWhere('h.holiday_date BETWEEN :start AND :end')
            ->andWhere('h.country_code = :country')
            ->setParameter('start', $start, Types::DATE_IMMUTABLE)
            ->setParameter('end', $end, Types::DATE_IMMUTABLE)
            ->setParameter('country', $countryCode)
            ->getQuery()
            ->getResult();
    }

    public function existsOnDate(\DateTimeInterface $date, string $countryCode = 'JP'): bool
    {
        $date = \DateTimeImmutable::createFromInterface($date);

        $count = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.holiday_date = :date')
            ->andWhere('h.country_code = :country')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('country', $countryCode)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }
}
