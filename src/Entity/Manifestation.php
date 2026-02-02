<?php

namespace App\Entity;

use App\Validator\ManifestationStatus;
use App\Repository\ManifestationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ManifestationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['identifier'], message: 'この識別子は既に使用されています')]
class Manifestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(mappedBy: 'manifestation', targetEntity: ManifestationAttachment::class, orphanRemoval: true)]
    private Collection $attachments;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $title_transcription = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $identifier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $external_identifier1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $external_identifier2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $external_identifier3 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buyer = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $buyer_identifier = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $purchase_date = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $record_source = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type4 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contributor1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contributor2 = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $loan_restriction = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $release_date_string = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $price_currency = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $class1 = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $class2 = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $extinfo = null;

    #[ORM\Column]
    #[ManifestationStatus]
    private string $status1 = 'Available';

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $status2 = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getTitleTranscription(): ?string
    {
        return $this->title_transcription;
    }

    public function setTitleTranscription(?string $title_transcription): static
    {
        $this->title_transcription = $title_transcription;
        return $this;
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

    public function getExternalIdentifier1(): ?string
    {
        return $this->external_identifier1;
    }

    public function setExternalIdentifier1(?string $external_identifier1): static
    {
        $this->external_identifier1 = $external_identifier1;
        return $this;
    }

    public function getExternalIdentifier2(): ?string
    {
        return $this->external_identifier2;
    }

    public function setExternalIdentifier2(?string $external_identifier2): static
    {
        $this->external_identifier2 = $external_identifier2;
        return $this;
    }

    public function getExternalIdentifier3(): ?string
    {
        return $this->external_identifier3;
    }

    public function setExternalIdentifier3(?string $external_identifier3): static
    {
        $this->external_identifier3 = $external_identifier3;
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

    public function getBuyer(): ?string
    {
        return $this->buyer;
    }

    public function setBuyer(?string $buyer): static
    {
        $this->buyer = $buyer;
        return $this;
    }

    public function getBuyerIdentifier(): ?string
    {
        return $this->buyer_identifier;
    }

    public function setBuyerIdentifier(?string $buyer_identifier): static
    {
        $this->buyer_identifier = $buyer_identifier;
        return $this;
    }

    public function getPurchaseDate(): ?\DateTimeInterface
    {
        return $this->purchase_date;
    }

    public function setPurchaseDate(?\DateTimeInterface $purchase_date): static
    {
        $this->purchase_date = $purchase_date;
        return $this;
    }

    public function getRecordSource(): ?string
    {
        return $this->record_source;
    }

    public function setRecordSource(?string $record_source): static
    {
        $this->record_source = $record_source;
        return $this;
    }

    public function getType1(): ?string
    {
        return $this->type1;
    }

    public function setType1(?string $type1): static
    {
        $this->type1 = $type1;
        return $this;
    }

    public function getType2(): ?string
    {
        return $this->type2;
    }

    public function setType2(?string $type2): static
    {
        $this->type2 = $type2;
        return $this;
    }

    public function getType3(): ?string
    {
        return $this->type3;
    }

    public function setType3(?string $type3): static
    {
        $this->type3 = $type3;
        return $this;
    }

    public function getType4(): ?string
    {
        return $this->type4;
    }

    public function setType4(?string $type4): static
    {
        $this->type4 = $type4;
        return $this;
    }

    public function getLocation1(): ?string
    {
        return $this->location1;
    }

    public function setLocation1(?string $location1): static
    {
        $this->location1 = $location1;
        return $this;
    }

    public function getLocation2(): ?string
    {
        return $this->location2;
    }

    public function setLocation2(?string $location2): static
    {
        $this->location2 = $location2;
        return $this;
    }

    public function getLocation3(): ?string
    {
        return $this->location3;
    }

    public function setLocation3(?string $location3): static
    {
        $this->location3 = $location3;
        return $this;
    }

    public function getContributor1(): ?string
    {
        return $this->contributor1;
    }

    public function setContributor1(?string $contributor1): static
    {
        $this->contributor1 = $contributor1;
        return $this;
    }

    public function getContributor2(): ?string
    {
        return $this->contributor2;
    }

    public function setContributor2(?string $contributor2): static
    {
        $this->contributor2 = $contributor2;
        return $this;
    }

    public function getLoanRestriction(): ?string
    {
        return $this->loan_restriction;
    }

    public function setLoanRestriction(?string $loan_restriction): static
    {
        $this->loan_restriction = $loan_restriction;
        return $this;
    }

    public function getReleaseDateString(): ?string
    {
        return $this->release_date_string;
    }

    public function setReleaseDateString(?string $release_date_string): static
    {
        $this->release_date_string = $release_date_string;
        return $this;
    }

    public function getFormattedPrice(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        $formatted = number_format($this->price, 0, '.', ',');
        $currency = $this->price_currency !== null ? strtoupper($this->price_currency) : null;
        if ($currency === null || $currency === '' || $currency === 'JPY') {
            return $formatted . '円';
        }

        return $formatted . ' ' . $currency;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice($price): self
    {
        if (is_string($price)) {
            // カンマや通貨記号を除去し、数値のみにする
            $price = str_replace([',', '￥', '¥'], '', $price);
            $price = filter_var($price, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }

        $this->price = $price !== '' && $price !== null ? (string)$price : null;

        return $this;
    }

    public function getPriceCurrency(): ?string
    {
        return $this->price_currency;
    }

    public function setPriceCurrency(?string $price_currency): static
    {
        if ($price_currency !== null) {
            $price_currency = strtoupper(trim($price_currency));
            if ($price_currency === '') {
                $price_currency = null;
            }
        }
        $this->price_currency = $price_currency;
        return $this;
    }

    public function getClass1(): ?string
    {
        return $this->class1;
    }

    public function setClass1(?string $class1): static
    {
        $this->class1 = $class1;

        return $this;
    }

    public function getClass2(): ?string
    {
        return $this->class2;
    }

    public function setClass2(?string $class2): static
    {
        $this->class2 = $class2;

        return $this;
    }

    public function getExtinfo(): ?string
    {
        return $this->extinfo;
    }

    public function setExtinfo(?string $extinfo): static
    {
        $this->extinfo = $extinfo;

        return $this;
    }

    public function getStatus1(): ?string
    {
        return $this->status1;
    }

    public function setStatus1(string $status1): static
    {
        $this->status1 = $status1;
        return $this;
    }

    public function getStatus2(): ?string
    {
        return $this->status2;
    }

    public function setStatus2(?string $status2): static
    {
        $this->status2 = $status2;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    /**
     * @return Collection<int, ManifestationAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(ManifestationAttachment $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setManifestation($this);
        }

        return $this;
    }

    public function removeAttachment(ManifestationAttachment $attachment): static
    {
        if ($this->attachments->removeElement($attachment)) {
            // set the owning side to null (unless already changed)
            if ($attachment->getManifestation() === $this) {
                $attachment->setManifestation(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
        
        // status1のデフォルト値を設定
        if (empty($this->status1)) {
            $this->status1 = 'Available';
        }
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }

    public function getAmazonUrl(): ?string
    {
        // externalIdentifier3がASINとして使われていると仮定
        if (empty($this->getExternalIdentifier3())) {
            return null;
        }

        $domain = 'amazon.co.jp';
        $buyer = $this->buyer ?? '';
        if ($buyer !== '' && str_contains(strtolower($buyer), 'amazon.com')) {
            $domain = 'amazon.com';
        }

        return 'https://www.' . $domain . '/dp/' . $this->getExternalIdentifier3();
    }

}
