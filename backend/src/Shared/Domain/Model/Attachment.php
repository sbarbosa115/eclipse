<?php

namespace App\Shared\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A file attached to a document (§4.6 footer): the supplier's PDF or XML on a purchase invoice, a support on a
 * receipt. Stored under UPLOADS_DIR by its id, never by the name the person gave it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'attachment')]
#[ORM\Index(name: 'attachment_owner', columns: ['company_id', 'owner_type', 'owner_id'])]
class Attachment implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 40)]
        private string $ownerType,
        #[ORM\Column(type: 'uuid')]
        private Uuid $ownerId,
        #[ORM\Column(length: 255)]
        private string $fileName,
        #[ORM\Column(length: 100)]
        private string $contentType,
        #[ORM\Column]
        private int $size,
        #[ORM\Column(type: 'uuid')]
        private Uuid $uploadedBy,
        #[ORM\Column]
        private \DateTimeImmutable $uploadedAt,
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

    public function ownerType(): string
    {
        return $this->ownerType;
    }

    public function ownerId(): Uuid
    {
        return $this->ownerId;
    }

    public function fileName(): string
    {
        return $this->fileName;
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function uploadedBy(): Uuid
    {
        return $this->uploadedBy;
    }

    public function uploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    /** Where the bytes live, under UPLOADS_DIR. */
    public function storageKey(): string
    {
        return \sprintf('%s/%s', $this->companyId->toRfc4122(), $this->id->toRfc4122());
    }
}
