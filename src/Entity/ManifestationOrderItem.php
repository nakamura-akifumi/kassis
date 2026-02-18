<?php

namespace App\Entity;

use App\Repository\ManifestationOrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManifestationOrderItemRepository::class)]
#[ORM\Table(name: 'manifestation_order_item')]
#[ORM\UniqueConstraint(name: 'uniq_order_item_manifestation', columns: ['order_id', 'manifestation_id'])]
class ManifestationOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ManifestationOrder $order = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Manifestation $manifestation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $received_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?ManifestationOrder
    {
        return $this->order;
    }

    public function setOrder(?ManifestationOrder $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getManifestation(): ?Manifestation
    {
        return $this->manifestation;
    }

    public function setManifestation(?Manifestation $manifestation): static
    {
        $this->manifestation = $manifestation;
        return $this;
    }

    public function getReceivedAt(): ?\DateTimeInterface
    {
        return $this->received_at;
    }

    public function setReceivedAt(?\DateTimeInterface $received_at): static
    {
        $this->received_at = $received_at;
        return $this;
    }
}
