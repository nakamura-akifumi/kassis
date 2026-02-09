<?php

namespace App\Entity;

use App\Repository\LoanGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoanGroupRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'loan_group')]
class LoanGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * @var Collection<int, LoanGroupType1>
     */
    #[ORM\OneToMany(mappedBy: 'loanGroup', targetEntity: LoanGroupType1::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $type1Mappings;

    public function __construct()
    {
        $this->type1Mappings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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
     * @return Collection<int, LoanGroupType1>
     */
    public function getType1Mappings(): Collection
    {
        return $this->type1Mappings;
    }

    /**
     * @return string[]
     */
    public function getType1Identifiers(): array
    {
        $ids = [];
        foreach ($this->type1Mappings as $mapping) {
            $ids[] = $mapping->getType1Identifier();
        }
        return $ids;
    }

    /**
     * @param string[] $identifiers
     */
    public function setType1Identifiers(array $identifiers): void
    {
        foreach ($this->type1Mappings->toArray() as $mapping) {
            $this->removeType1Mapping($mapping);
        }
        foreach ($identifiers as $identifier) {
            $this->addType1Identifier($identifier);
        }
    }

    public function addType1Identifier(string $identifier): void
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return;
        }
        foreach ($this->type1Mappings as $mapping) {
            if ($mapping->getType1Identifier() === $identifier) {
                return;
            }
        }
        $mapping = new LoanGroupType1();
        $mapping->setType1Identifier($identifier);
        $mapping->setLoanGroup($this);
        $this->type1Mappings->add($mapping);
    }

    public function removeType1Mapping(LoanGroupType1 $mapping): void
    {
        if ($this->type1Mappings->removeElement($mapping)) {
            $mapping->setLoanGroup(null);
        }
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $now = new \DateTime();
        $this->created_at = $now;
        $this->updated_at = $now;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }
}
