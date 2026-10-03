<?php

namespace App\Company\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * The DIAN authorisation to invoice (§4.1): a prefix, a range desde–hasta and a validity period. One per company in
 * stage 1 (§9 Q12). Its next number is taken under a row lock when a sales invoice is emitted, so two emissions never
 * share a number and a number is never reused.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoicing_resolution')]
#[ORM\Index(name: 'invoicing_resolution_company', columns: ['company_id'])]
class InvoicingResolution implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column]
    private int $nextNumber;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 40)]
        private string $resolutionNumber,
        #[ORM\Column(length: 10)]
        private string $prefix,
        #[ORM\Column]
        private int $rangeFrom,
        #[ORM\Column]
        private int $rangeTo,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $validFrom,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $validTo,
        #[ORM\Column(length: 12, enumType: InvoicingMode::class)]
        private InvoicingMode $mode,
    ) {
        $this->id = Uuid::v7();
        $this->nextNumber = $rangeFrom;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function resolutionNumber(): string
    {
        return $this->resolutionNumber;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function rangeFrom(): int
    {
        return $this->rangeFrom;
    }

    public function rangeTo(): int
    {
        return $this->rangeTo;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): \DateTimeImmutable
    {
        return $this->validTo;
    }

    public function mode(): InvoicingMode
    {
        return $this->mode;
    }

    public function nextNumber(): int
    {
        return $this->nextNumber;
    }
}
