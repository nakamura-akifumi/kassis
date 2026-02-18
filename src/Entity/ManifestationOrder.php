<?php

namespace App\Entity;

use App\Repository\ManifestationOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManifestationOrderRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'manifestation_order')]
class ManifestationOrder
{
    public const STATUS_ORDERED = 'Ordered';
    public const STATUS_COMPLETED = 'Completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $order_number;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_ORDERED;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $ordered_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completed_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * @var Collection<int, ManifestationOrderItem>
     */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: ManifestationOrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->order_number;
    }

    public function setOrderNumber(string $order_number): static
    {
        $this->order_number = $order_number;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getOrderedAt(): ?\DateTimeInterface
    {
        return $this->ordered_at;
    }

    public function setOrderedAt(?\DateTimeInterface $ordered_at): static
    {
        $this->ordered_at = $ordered_at;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completed_at;
    }

    public function setCompletedAt(?\DateTimeInterface $completed_at): static
    {
        $this->completed_at = $completed_at;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    /**
     * @return Collection<int, ManifestationOrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ManifestationOrderItem $item): void
    {
        if ($this->items->contains($item)) {
            return;
        }
        $this->items->add($item);
        $item->setOrder($this);
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $now = new \DateTime();
        $this->created_at = $now;
        $this->updated_at = $now;
        if ($this->ordered_at === null) {
            $this->ordered_at = $now;
        }
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }
}
