<?php

namespace App\Repository;

use App\Entity\Checkout;
use App\Entity\LoanGroupType1;
use App\Entity\Manifestation;
use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Checkout>
 */
class CheckoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Checkout::class);
    }

    public function findActiveByManifestation(Manifestation $manifestation): ?Checkout
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.manifestation = :manifestation')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('manifestation', $manifestation)
            ->setParameter('statuses', [Checkout::STATUS_CHECKED_OUT, '貸出中'])
            ->orderBy('c.checked_out_at', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestByManifestation(Manifestation $manifestation): ?Checkout
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.manifestation = :manifestation')
            ->setParameter('manifestation', $manifestation)
            ->orderBy('c.checked_out_at', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Checkout[]
     */
    public function findRecentActive(int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('statuses', [Checkout::STATUS_CHECKED_OUT, '貸出中'])
            ->orderBy('c.checked_out_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Checkout[]
     */
    public function findRecentReturned(int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('statuses', [Checkout::STATUS_RETURNED, '返却済'])
            ->orderBy('c.checked_in_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActiveByMember(Member $member): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.member = :member')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('member', $member)
            ->setParameter('statuses', [Checkout::STATUS_CHECKED_OUT, '貸出中'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, array{loan_group_id: int, cnt: string}>
     */
    public function countActiveByMemberGroupedByLoanGroup(Member $member): array
    {
        return $this->createQueryBuilder('c')
            ->select('lg.id AS loan_group_id')
            ->addSelect('COUNT(m.id) AS cnt')
            ->innerJoin('c.member', 'mem')
            ->innerJoin('c.manifestation', 'm')
            ->innerJoin(LoanGroupType1::class, 'lgt1', 'WITH', 'm.type1 = lgt1.type1_identifier')
            ->innerJoin('lgt1.loanGroup', 'lg')
            ->andWhere('c.status <> :returned')
            ->andWhere('mem = :member')
            ->groupBy('lg.id')
            ->setParameter('returned', Checkout::STATUS_RETURNED)
            ->setParameter('member', $member)
            ->getQuery()
            ->getArrayResult();
    }
}
