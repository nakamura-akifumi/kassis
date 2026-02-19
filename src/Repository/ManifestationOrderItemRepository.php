<?php

namespace App\Repository;

use App\Entity\Manifestation;
use App\Entity\ManifestationOrder;
use App\Entity\ManifestationOrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ManifestationOrderItem>
 */
class ManifestationOrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ManifestationOrderItem::class);
    }

    public function findOneByOrderAndManifestation(ManifestationOrder $order, Manifestation $manifestation): ?ManifestationOrderItem
    {
        return $this->findOneBy([
            'order' => $order,
            'manifestation' => $manifestation,
        ]);
    }

    /**
     * @return ManifestationOrderItem[]
     */
    public function findAwaitingByOrder(ManifestationOrder $order): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.manifestation', 'm')
            ->addSelect('m')
            ->andWhere('i.order = :order')
            ->andWhere('m.status1 = :status')
            ->setParameter('order', $order)
            ->setParameter('status', 'Awaiting Order')
            ->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ManifestationOrderItem[]
     */
    public function findByOrderWithManifestations(ManifestationOrder $order): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.manifestation', 'm')
            ->addSelect('m')
            ->andWhere('i.order = :order')
            ->setParameter('order', $order)
            ->orderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
