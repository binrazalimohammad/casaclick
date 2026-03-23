<?php

namespace App\Entity;

use App\Repository\ApplicationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
class Application
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'App\\Entity\\Product')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $listing = null;

    #[ORM\ManyToOne(targetEntity: 'App\\Entity\\User')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $tenant = null;

    #[ORM\ManyToOne(targetEntity: 'App\\Entity\\User')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $landlord = null;

    #[ORM\Column(length: 50)]
    private string $status = 'pending'; // 'pending', 'approved', 'rejected', 'completed'

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: 'App\\Entity\\Payment', mappedBy: 'application', cascade: ['persist', 'remove'])]
    private Collection $payments;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListing(): ?Product
    {
        return $this->listing;
    }

    public function setListing(?Product $listing): static
    {
        $this->listing = $listing;
        return $this;
    }

    public function getTenant(): ?User
    {
        return $this->tenant;
    }

    public function setTenant(?User $tenant): static
    {
        $this->tenant = $tenant;
        return $this;
    }

    public function getLandlord(): ?User
    {
        return $this->landlord;
    }

    public function setLandlord(?User $landlord): static
    {
        $this->landlord = $landlord;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setApplication($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getApplication() === $this) {
                $payment->setApplication(null);
            }
        }

        return $this;
    }
}

