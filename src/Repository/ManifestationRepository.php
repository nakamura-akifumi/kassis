<?php

namespace App\Repository;

use App\Entity\Manifestation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use App\Service\ManifestationSearchQuery;

class ManifestationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Manifestation::class);
    }

    public function advancedSearch(
        ?string $q,
        ?string $identifier,
        ?string $external_id1,
        ?string $type1,
        ?string $type2,
        ?string $sort,
    ) {
        $qb = $this->createQueryBuilder('m');

        if ($q) {
            $keywords = preg_split('/[\s\n\r]+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY);
            
            // 改行が含まれている場合は「識別子のみ」を検索対象とする
            $isMultiLine = str_contains($q, "\n");

            if ($isMultiLine) {
                // 複数行入力時は識別子のいずれかに一致(OR)
                $orX = $qb->expr()->orX();
                foreach ($keywords as $key => $keyword) {
                    $paramName = 'q_' . $key;
                    $orX->add("m.identifier = :$paramName"); // 完全一致の方が識別子検索としては一般的
                    $qb->setParameter($paramName, $keyword);
                }
                $qb->andWhere($orX);
            } else {
                // 1行入力時は従来通りタイトル等も含める(AND)
                foreach ($keywords as $key => $keyword) {
                    $paramName = 'q_' . $key;
                    $qb->andWhere("(m.title LIKE :$paramName OR m.identifier LIKE :$paramName OR m.description LIKE :$paramName)")
                       ->setParameter($paramName, '%' . $keyword . '%');
                }
            }
        }

        if ($identifier) {
            $qb->andWhere('m.identifier LIKE :identifier')
                ->setParameter('identifier', '%' . $identifier . '%');
        }

        if ($external_id1) {
            $qb->andWhere('m.external_identifier1 LIKE :external_id1')
                ->setParameter('external_id1', '%' . $external_id1 . '%');
        }

        if ($type1) {
            $qb->andWhere('m.type1 = :type1')
                ->setParameter('type1', $type1);
        }

        if ($type2) {
            $qb->andWhere('m.type2 = :type2')
                ->setParameter('type2', $type2);
        }
/*
        if ($purchase_date_from) {
            $qb->andWhere('m.purchase_date >= :purchase_date_from')
                ->setParameter('purchase_date_from', $purchase_date_from);
        }

        if ($purchase_date_to) {
            $qb->andWhere('m.purchase_date <= :purchase_date_to')
                ->setParameter('purchase_date_to', $purchase_date_to);
        }
*/
        return $this->applySort($qb, $sort)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Manifestation[] Returns an array of Manifestation objects
     */
    public function searchByQuery(ManifestationSearchQuery $query): array
    {
        if (!$query->hasSearchCriteria()) {
            return $this->applySort($this->createQueryBuilder('m'), $query->sort)
                ->getQuery()
                ->getResult();
        }

        $results = $this->advancedSearch(
            $query->q,
            $query->identifier,
            $query->externalId1,
            $query->type1,
            $query->type2,
            $query->sort,
        );

        // 複数行入力（識別子リスト）の場合は、入力順に並べ替える
        if ($query->isMultiLine() && ($query->sort === null || $query->sort === '')) {
            $keywords = preg_split('/[\s\n\r]+/u', trim($query->q), -1, PREG_SPLIT_NO_EMPTY);
            $keywordOrder = array_flip($keywords);

            usort($results, function($a, $b) use ($keywordOrder) {
                $orderA = $keywordOrder[$a->getIdentifier()] ?? 99999;
                $orderB = $keywordOrder[$b->getIdentifier()] ?? 99999;
                return $orderA <=> $orderB;
            });
        }

        return $results;
    }

    private function applySort(QueryBuilder $qb, ?string $sort): QueryBuilder
    {
        $sort = $sort ?? 'created_at_asc';
        switch ($sort) {
            case 'created_at_desc':
                $qb->orderBy('m.created_at', 'DESC');
                break;
            case 'title_asc':
                $qb->orderBy('m.title', 'ASC');
                break;
            case 'title_desc':
                $qb->orderBy('m.title', 'DESC');
                break;
            case 'identifier_asc':
                $qb->orderBy('m.identifier', 'ASC');
                break;
            case 'identifier_desc':
                $qb->orderBy('m.identifier', 'DESC');
                break;
            case 'release_date_end_asc':
                $qb->orderBy('m.release_date_end', 'ASC');
                break;
            case 'release_date_end_desc':
                $qb->orderBy('m.release_date_end', 'DESC');
                break;
            case 'created_at_asc':
            default:
                $qb->orderBy('m.created_at', 'ASC');
                break;
        }

        return $qb->addOrderBy('m.id', 'ASC');
    }
}
