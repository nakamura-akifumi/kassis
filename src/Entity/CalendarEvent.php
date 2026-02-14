<?php

namespace App\Entity;

use App\Repository\CalendarEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalendarEventRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'calendar_event')]
#[ORM\UniqueConstraint(name: 'uniq_calendar_event_uid_recurrence', columns: ['uid', 'recurrence_id'])]
class CalendarEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $uid;

    #[ORM\Column(type: Types::TEXT)]
    private string $summary = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dt_start = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dt_end = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $all_day = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $recurrence_id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rrule = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rdate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $exdate = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $transparency = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $organizer = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $is_closed = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUid(string $uid): static
    {
        $this->uid = $uid;
        return $this;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getDtStart(): ?\DateTimeInterface
    {
        return $this->dt_start;
    }

    public function setDtStart(\DateTimeInterface $dt_start): static
    {
        $this->dt_start = $dt_start;
        return $this;
    }

    public function getDtEnd(): ?\DateTimeInterface
    {
        return $this->dt_end;
    }

    public function setDtEnd(?\DateTimeInterface $dt_end): static
    {
        $this->dt_end = $dt_end;
        return $this;
    }

    public function isAllDay(): bool
    {
        return $this->all_day;
    }

    public function setAllDay(bool $all_day): static
    {
        $this->all_day = $all_day;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getRecurrenceId(): ?\DateTimeInterface
    {
        return $this->recurrence_id;
    }

    public function setRecurrenceId(?\DateTimeInterface $recurrence_id): static
    {
        $this->recurrence_id = $recurrence_id;
        return $this;
    }

    public function getRrule(): ?string
    {
        return $this->rrule;
    }

    public function setRrule(?string $rrule): static
    {
        $this->rrule = $rrule;
        return $this;
    }

    public function getRdate(): ?string
    {
        return $this->rdate;
    }

    public function setRdate(?string $rdate): static
    {
        $this->rdate = $rdate;
        return $this;
    }

    public function getExdate(): ?string
    {
        return $this->exdate;
    }

    public function setExdate(?string $exdate): static
    {
        $this->exdate = $exdate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTransparency(): ?string
    {
        return $this->transparency;
    }

    public function setTransparency(?string $transparency): static
    {
        $this->transparency = $transparency;
        return $this;
    }

    public function getOrganizer(): ?string
    {
        return $this->organizer;
    }

    public function setOrganizer(?string $organizer): static
    {
        $this->organizer = $organizer;
        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(?int $sequence): static
    {
        $this->sequence = $sequence;
        return $this;
    }

    public function isClosed(): bool
    {
        return $this->is_closed;
    }

    public function setIsClosed(bool $is_closed): static
    {
        $this->is_closed = $is_closed;
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
