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
}
