<?php

namespace App\Entity;

use App\Repository\LoanGroupType1Repository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoanGroupType1Repository::class)]
#[ORM\Table(name: 'loan_group_type1')]
#[ORM\UniqueConstraint(name: 'uniq_loan_group_type1_identifier', columns: ['type1_identifier'])]
class LoanGroupType1
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'type1Mappings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LoanGroup $loanGroup = null;

    #[ORM\Column(length: 255)]
    private string $type1_identifier;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoanGroup(): ?LoanGroup
    {
        return $this->loanGroup;
    }

    public function setLoanGroup(?LoanGroup $loanGroup): static
    {
        $this->loanGroup = $loanGroup;
        return $this;
    }

    public function getType1Identifier(): string
    {
        return $this->type1_identifier;
    }

    public function setType1Identifier(string $type1_identifier): static
    {
        $this->type1_identifier = $type1_identifier;
        return $this;
    }
}
