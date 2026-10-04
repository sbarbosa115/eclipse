<?php

namespace App\Shared\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One change a person made that the documents' own history does not show: a setting, a posting rule, a role, the
 * lock date, the manual-invoicing confirmation (§5 cross-stage NFR "Auditability"). Written once, never changed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'audit_log_company_at', columns: ['company_id', 'occurred_at'])]
class AuditLog implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /**
     * @param array<string, mixed> $data what changed, from and to
     */
    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $userId,
        #[ORM\Column(length: 80)]
        private string $action,
        #[ORM\Column(length: 40)]
        private string $subjectType,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $subjectId,
        #[ORM\Column(type: Types::JSON)]
        private array $data,
        #[ORM\Column]
        private \DateTimeImmutable $occurredAt,
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function userId(): ?Uuid
    {
        return $this->userId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function subjectType(): string
    {
        return $this->subjectType;
    }

    public function subjectId(): ?Uuid
    {
        return $this->subjectId;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
