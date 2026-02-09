<?php

namespace App\Repository;

use App\Entity\LoanGroupType1;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanGroupType1>
 */
class LoanGroupType1Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanGroupType1::class);
    }

    /**
     * @param string[] $type1Identifiers
     * @return array<string, string> identifier => group name
     */
    public function findConflicts(array $type1Identifiers, ?int $excludeGroupId = null): array
    {
        $type1Identifiers = array_values(array_unique(array_filter($type1Identifiers, static fn ($v) => $v !== '')));
        if ($type1Identifiers === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('m')
            ->select('m.type1_identifier AS type1Identifier', 'g.name AS groupName', 'g.id AS groupId')
            ->join('m.loanGroup', 'g')
            ->where('m.type1_identifier IN (:identifiers)')
            ->setParameter('identifiers', $type1Identifiers);

        if ($excludeGroupId !== null) {
            $qb->andWhere('g.id != :excludeId')
                ->setParameter('excludeId', $excludeGroupId);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $conflicts = [];
        foreach ($rows as $row) {
            $identifier = (string) ($row['type1Identifier'] ?? '');
            $groupName = (string) ($row['groupName'] ?? '');
            if ($identifier !== '') {
                $conflicts[$identifier] = $groupName;
            }
        }

        return $conflicts;
    }
}
