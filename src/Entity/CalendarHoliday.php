<?php

namespace App\Entity;

use App\Repository\CalendarHolidayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalendarHolidayRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'calendar_holiday')]
#[ORM\UniqueConstraint(name: 'uniq_calendar_holiday_date_country', columns: ['holiday_date', 'country_code'])]
class CalendarHoliday
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $holiday_date = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 2)]
    private string $country_code = 'JP';

    #[ORM\Column(length: 64)]
    private string $source = 'yasumi';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHolidayDate(): ?\DateTimeInterface
    {
        return $this->holiday_date;
    }

    public function setHolidayDate(\DateTimeInterface $holiday_date): static
    {
        $this->holiday_date = $holiday_date;
        return $this;
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

    public function getCountryCode(): string
    {
        return $this->country_code;
    }

    public function setCountryCode(string $country_code): static
    {
        $this->country_code = $country_code;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
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
