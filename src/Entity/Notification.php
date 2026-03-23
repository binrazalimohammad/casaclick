<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'App\\Entity\\User')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private string $type; // 'listing_created', 'application_submitted', 'application_approved'

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $relatedEntity = null; // 'Product', 'Application'

    #[ORM\Column(nullable: true)]
    private ?int $relatedId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getRelatedEntity(): ?string
    {
        return $this->relatedEntity;
    }

    public function setRelatedEntity(?string $relatedEntity): static
    {
        $this->relatedEntity = $relatedEntity;
        return $this;
    }

    public function getRelatedId(): ?int
    {
        return $this->relatedId;
    }

    public function setRelatedId(?int $relatedId): static
    {
        $this->relatedId = $relatedId;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
