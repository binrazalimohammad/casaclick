<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'App\\Entity\\User')]
    private ?User $user = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 50)]
    private string $action;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetEntity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $targetData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $username): static { $this->username = $username; return $this; }
    public function getRole(): ?string { return $this->role; }
    public function setRole(?string $role): static { $this->role = $role; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
    public function getTargetEntity(): ?string { return $this->targetEntity; }
    public function setTargetEntity(?string $t): static { $this->targetEntity = $t; return $this; }
    public function getTargetId(): ?string { return $this->targetId; }
    public function setTargetId(?string $id): static { $this->targetId = $id; return $this; }
    public function getTargetData(): ?string { return $this->targetData; }
    public function setTargetData(?string $data): static { $this->targetData = $data; return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $d): static { $this->details = $d; return $this; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
