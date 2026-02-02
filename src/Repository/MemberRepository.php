<?php

namespace App\Repository;

use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Member>
 */
class MemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Member::class);
    }

    /**
     * @return Member[]
     */
    public function findBySearchTerm(?string $term): array
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $this->findBy([], ['id' => 'DESC']);
        }

        $qb = $this->createQueryBuilder('m');
        $likeTerm = '%' . $term . '%';

        return $qb
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('m.full_name', ':term'),
                    $qb->expr()->like('m.full_name_transcription', ':term'),
                    $qb->expr()->like('m.communication_address1', ':term'),
                    $qb->expr()->like('m.communication_address2', ':term'),
                )
            )
            ->setParameter('term', $likeTerm)
            ->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
