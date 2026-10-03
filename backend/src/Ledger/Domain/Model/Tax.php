<?php

namespace App\Ledger\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Totals\TaxCalculation;
use Doctrine\DBAL\Types\Types;
use App\Shared\Domain\Model\References;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A tax of the company's catalog (§4.4): seeded, editable by the accountant, never deleted once a document used it
 * (deactivated instead). Each posts to one account on sales and one on purchases (null: the kind's posting rule).
 * Rates carry validity dates; a document copies the tax when a line uses it (Shared TaxSnapshot).
 */
#[ORM\Entity]
#[ORM\Table(name: 'tax')]
#[ORM\Index(name: 'tax_company_class', columns: ['company_id', 'tax_class', 'active'])]
class Tax implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 80)]
        private string $name,
        #[ORM\Column(length: 12, enumType: TaxClass::class)]
        private TaxClass $taxClass,
        #[ORM\Column(length: 12, enumType: TaxKind::class)]
        private TaxKind $kind,
        #[ORM\Column(length: 12, enumType: TaxCalculation::class)]
        private TaxCalculation $calculation,
        /** A percentage ("19.0000") or a value per unit ("500.0000"). */
        #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 4)]
        private string $rate,
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('ledger_account')]
        private ?Uuid $salesAccountId,
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('ledger_account')]
        private ?Uuid $purchaseAccountId,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $validFrom = null,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $validTo = null,
        /** From the seed. */
        #[ORM\Column]
        private bool $standard = false,
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

    public function name(): string
    {
        return $this->name;
    }

    public function taxClass(): TaxClass
    {
        return $this->taxClass;
    }

    public function kind(): TaxKind
    {
        return $this->kind;
    }

    public function calculation(): TaxCalculation
    {
        return $this->calculation;
    }

    public function rate(): string
    {
        return $this->rate;
    }

    public function salesAccountId(): ?Uuid
    {
        return $this->salesAccountId;
    }

    public function purchaseAccountId(): ?Uuid
    {
        return $this->purchaseAccountId;
    }

    public function validFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isStandard(): bool
    {
        return $this->standard;
    }
}
