<?php

namespace App\Shared\Domain\Model;

use App\Shared\Domain\Totals\TaxCalculation;
use App\Shared\Domain\Totals\TaxRate;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A tax as it was when a line used it: which one (null: none), its name, kind (iva, retefuente…), how it is computed,
 * its rate and the account it posts to on this side (sales or purchases). Ledger's Tax is the catalog; this is the
 * copy a document keeps.
 */
#[ORM\Embeddable]
final class TaxSnapshot
{
    public function __construct(
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $taxId,
        #[ORM\Column(length: 80)]
        private string $name,
        #[ORM\Column(length: 20)]
        private string $kind,
        #[ORM\Column(length: 12, enumType: TaxCalculation::class)]
        private TaxCalculation $calculation,
        /** A percentage ("19.0000") or a value per unit ("500.0000"). */
        #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 4)]
        private string $value,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $accountId,
    ) {
    }

    public static function none(): self
    {
        return new self(null, 'Ninguno', 'none', TaxCalculation::Percentage, '0.0000', null);
    }

    public function rate(): TaxRate
    {
        return match ($this->calculation) {
            // ReteIVA is a percentage of the line's IVA, whichever document holds it.
            TaxCalculation::Percentage => 'reteiva' === $this->kind ? TaxRate::percentageOfTax($this->value) : TaxRate::percentage($this->value),
            TaxCalculation::PerUnit => TaxRate::perUnit($this->value),
        };
    }

    public function isNone(): bool
    {
        return null === $this->taxId;
    }

    public function taxId(): ?Uuid
    {
        return $this->taxId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function calculation(): TaxCalculation
    {
        return $this->calculation;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function accountId(): ?Uuid
    {
        return $this->accountId;
    }
}
