<?php

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['identifier'], message: 'この識別子は既に使用されています')]
class Member
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => '有効',
        self::STATUS_INACTIVE => '無効',
        self::STATUS_EXPIRED => '期限切れ',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: '識別子は必須です。')]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9]+$/', message: '識別子は英数字のみで入力してください。')]
    private ?string $identifier = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'フルネームは必須です。')]
    private ?string $full_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $full_name_transcription = null;

    #[ORM\Column(length: 32)]
    private string $group1 = 'standard';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $group2 = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $communication_address1 = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $communication_address2 = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiry_date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->full_name;
    }

    public function setFullName(string $full_name): static
    {
        $this->full_name = $full_name;
        return $this;
    }

    public function getFullNameTranscription(): ?string
    {
        return $this->full_name_transcription;
    }

    public function setFullNameTranscription(?string $full_name_transcription): static
    {
        $this->full_name_transcription = $full_name_transcription;
        return $this;
    }

    public function getGroup1(): string
    {
        return $this->group1;
    }

    public function setGroup1(?string $group1): static
    {
        $trimmed = trim((string) $group1);
        $this->group1 = $trimmed !== '' ? $trimmed : 'standard';
        return $this;
    }

    public function getGroup2(): ?string
    {
        return $this->group2;
    }

    public function setGroup2(?string $group2): static
    {
        $this->group2 = $group2;
        return $this;
    }

    public function getCommunicationAddress1(): ?string
    {
        return $this->communication_address1;
    }

    public function setCommunicationAddress1(?string $communication_address1): static
    {
        $this->communication_address1 = $communication_address1;
        return $this;
    }

    public function getCommunicationAddress2(): ?string
    {
        return $this->communication_address2;
    }

    public function setCommunicationAddress2(?string $communication_address2): static
    {
        $this->communication_address2 = $communication_address2;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;
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

    public function getStatusLabel(): ?string
    {
        if ($this->status === null) {
            return null;
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public static function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $trimmed = trim($status);
        if ($trimmed === '') {
            return null;
        }

        if (isset(self::STATUS_LABELS[$trimmed])) {
            return $trimmed;
        }

        $lower = strtolower($trimmed);
        foreach (self::STATUS_LABELS as $key => $label) {
            if ($label === $trimmed) {
                return $key;
            }
            if (strtolower($key) === $lower) {
                return $key;
            }
        }

        return null;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiry_date;
    }

    public function setExpiryDate(?\DateTimeInterface $expiry_date): static
    {
        $this->expiry_date = $expiry_date;
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
        if (trim($this->group1) === '') {
            $this->group1 = 'standard';
        }
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        if (trim($this->group1) === '') {
            $this->group1 = 'standard';
        }
        $this->updated_at = new \DateTime();
    }
}
