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
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_ORDERED = 'Ordered';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_DELETED = 'Deleted';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $order_number;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vendor = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $order_amount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $delivery_due_date = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $estimate_number = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $vendor_contact = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $order_contact = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $external_reference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $memo = null;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_IN_PROGRESS;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $ordered_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completed_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $exported_at = null;

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

    public function getVendor(): ?string
    {
        return $this->vendor;
    }

    public function setVendor(?string $vendor): static
    {
        $this->vendor = $vendor;
        return $this;
    }

    public function getOrderAmount(): ?int
    {
        return $this->order_amount;
    }

    public function setOrderAmount(?int $order_amount): static
    {
        $this->order_amount = $order_amount;
        return $this;
    }

    public function getDeliveryDueDate(): ?\DateTimeInterface
    {
        return $this->delivery_due_date;
    }

    public function setDeliveryDueDate(?\DateTimeInterface $delivery_due_date): static
    {
        $this->delivery_due_date = $delivery_due_date;
        return $this;
    }

    public function getEstimateNumber(): ?string
    {
        return $this->estimate_number;
    }

    public function setEstimateNumber(?string $estimate_number): static
    {
        $this->estimate_number = $estimate_number;
        return $this;
    }

    public function getVendorContact(): ?string
    {
        return $this->vendor_contact;
    }

    public function setVendorContact(?string $vendor_contact): static
    {
        $this->vendor_contact = $vendor_contact;
        return $this;
    }

    public function getOrderContact(): ?string
    {
        return $this->order_contact;
    }

    public function setOrderContact(?string $order_contact): static
    {
        $this->order_contact = $order_contact;
        return $this;
    }

    public function getExternalReference(): ?string
    {
        return $this->external_reference;
    }

    public function setExternalReference(?string $external_reference): static
    {
        $this->external_reference = $external_reference;
        return $this;
    }

    public function getMemo(): ?string
    {
        return $this->memo;
    }

    public function setMemo(?string $memo): static
    {
        $this->memo = $memo;
        return $this;
    }

    public function setCompletedAt(?\DateTimeInterface $completed_at): static
    {
        $this->completed_at = $completed_at;
        return $this;
    }

    public function getExportedAt(): ?\DateTimeInterface
    {
        return $this->exported_at;
    }

    public function setExportedAt(?\DateTimeInterface $exported_at): static
    {
        $this->exported_at = $exported_at;
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
        if ($this->ordered_at === null && $this->status === self::STATUS_ORDERED) {
            $this->ordered_at = $now;
        }
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }
}
