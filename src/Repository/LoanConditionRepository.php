<?php

namespace App\Repository;

use App\Entity\LoanCondition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanCondition>
 */
class LoanConditionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanCondition::class);
    }

    /**
     * @return LoanCondition[]
     */
    public function findAllSorted(?string $sort, string $allGroupLabel): array
    {
        $qb = $this->createQueryBuilder('lc')
            ->leftJoin('lc.loanGroup', 'lg')
            ->addSelect('lg');

        $groupExpr = "CASE WHEN lg.name IS NULL THEN :allGroupLabel ELSE lg.name END";
        $memberExpr = 'lc.member_group';

        if ($sort === 'member') {
            $qb->orderBy($memberExpr, 'ASC')
                ->addOrderBy($groupExpr, 'ASC');
        } else {
            $qb->orderBy($groupExpr, 'ASC')
                ->addOrderBy($memberExpr, 'ASC');
        }

        $qb->setParameter('allGroupLabel', $allGroupLabel);

        return $qb->getQuery()->getResult();
    }
}
