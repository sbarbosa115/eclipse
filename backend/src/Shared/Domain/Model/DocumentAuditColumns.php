<?php

namespace App\Shared\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Who created, emitted and voided a document, and when, with the reason for the void (§4.12, §4.14).
 */
trait DocumentAuditColumns
{
    #[ORM\Column(type: 'uuid')]
    private Uuid $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $emittedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emittedAt = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $voidedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $voidedAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $voidReason = null;

    private function recordCreation(Uuid $by, \DateTimeImmutable $at): void
    {
        $this->createdBy = $by;
        $this->createdAt = $at;
    }

    private function recordEmission(Uuid $by, \DateTimeImmutable $at): void
    {
        $this->emittedBy = $by;
        $this->emittedAt = $at;
    }

    private function recordVoid(Uuid $by, \DateTimeImmutable $at, string $reason): void
    {
        $this->voidedBy = $by;
        $this->voidedAt = $at;
        $this->voidReason = $reason;
    }

    public function createdBy(): Uuid
    {
        return $this->createdBy;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function emittedBy(): ?Uuid
    {
        return $this->emittedBy;
    }

    public function emittedAt(): ?\DateTimeImmutable
    {
        return $this->emittedAt;
    }

    public function voidedBy(): ?Uuid
    {
        return $this->voidedBy;
    }

    public function voidedAt(): ?\DateTimeImmutable
    {
        return $this->voidedAt;
    }

    public function voidReason(): ?string
    {
        return $this->voidReason;
    }
}
