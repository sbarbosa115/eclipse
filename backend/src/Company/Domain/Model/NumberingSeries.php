<?php

namespace App\Company\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A prefix and the next number of one kind of document. Taken under a row lock inside the emitting transaction
 * (Company\Application\Numbering), so numbers have no gaps from races and are never reused.
 */
#[ORM\Entity]
#[ORM\Table(name: 'numbering_series')]
#[ORM\UniqueConstraint(name: 'numbering_series_kind', columns: ['company_id', 'kind'])]
class NumberingSeries implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 30, enumType: SeriesKind::class)]
        private SeriesKind $kind,
        #[ORM\Column(length: 10)]
        private string $prefix,
        #[ORM\Column]
        private int $nextNumber = 1,
    ) {
        $this->id = Uuid::v7();
    }

    /** Hands out the next number and moves on. */
    public function take(): int
    {
        return $this->nextNumber++;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function kind(): SeriesKind
    {
        return $this->kind;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function nextNumber(): int
    {
        return $this->nextNumber;
    }
}
