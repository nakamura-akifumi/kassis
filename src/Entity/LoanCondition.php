<?php

namespace App\Entity;

use App\Repository\LoanConditionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoanConditionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'loan_condition')]
#[ORM\UniqueConstraint(name: 'uniq_loan_condition_group', columns: ['loan_group_id', 'member_group'])]
class LoanCondition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?LoanGroup $loanGroup = null;

    #[ORM\Column(length: 32)]
    private string $member_group;

    #[ORM\Column]
    private int $loan_limit = 0;

    #[ORM\Column]
    private int $loan_period = 0;

    #[ORM\Column]
    private int $renew_limit = 0;

    #[ORM\Column]
    private int $reservation_limit = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $adjust_due_on_closed_day = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

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

    public function getMemberGroup(): string
    {
        return $this->member_group;
    }

    public function setMemberGroup(string $member_group): static
    {
        $this->member_group = $member_group;
        return $this;
    }

    public function getLoanLimit(): int
    {
        return $this->loan_limit;
    }

    public function setLoanLimit(int $loan_limit): static
    {
        $this->loan_limit = $loan_limit;
        return $this;
    }

    public function getLoanPeriod(): int
    {
        return $this->loan_period;
    }

    public function setLoanPeriod(int $loan_period): static
    {
        $this->loan_period = $loan_period;
        return $this;
    }

    public function getRenewLimit(): int
    {
        return $this->renew_limit;
    }

    public function setRenewLimit(int $renew_limit): static
    {
        $this->renew_limit = $renew_limit;
        return $this;
    }

    public function getReservationLimit(): int
    {
        return $this->reservation_limit;
    }

    public function setReservationLimit(int $reservation_limit): static
    {
        $this->reservation_limit = $reservation_limit;
        return $this;
    }

    public function isAdjustDueOnClosedDay(): bool
    {
        return $this->adjust_due_on_closed_day;
    }

    public function setAdjustDueOnClosedDay(bool $adjust_due_on_closed_day): static
    {
        $this->adjust_due_on_closed_day = $adjust_due_on_closed_day;
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
