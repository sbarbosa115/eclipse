<?php

namespace App\Ledger\Domain\Model;

use App\Ledger\Domain\Error\InvalidTaxDefinition;
use App\Ledger\Domain\Error\TaxNotEditable;
use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\References;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxCalculation;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Types\Types;
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

    /**
     * A tax the accountant defines (§4.4), checked: the kind belongs to the class, a value per unit is only for
     * impoconsumo, the rate is a percentage from 0 to 100 (or zero or more per unit) with four decimals, and the
     * validity dates are in order.
     *
     * @throws InvalidTaxDefinition on the field at fault
     */
    public static function define(
        Uuid $companyId,
        string $name,
        TaxClass $taxClass,
        TaxKind $kind,
        TaxCalculation $calculation,
        string $rate,
        ?Uuid $salesAccountId,
        ?Uuid $purchaseAccountId,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null,
        bool $standard = false,
    ): self {
        if (!self::kindBelongsTo($taxClass, $kind)) {
            throw new InvalidTaxDefinition('kind', 'This kind of tax does not belong to this class.');
        }
        $rate = self::checkedRate($kind, $taxClass, $calculation, $rate);
        self::checkDates($validFrom, $validTo);

        return new self($companyId, trim($name), $taxClass, $kind, $calculation, $rate, $salesAccountId, $purchaseAccountId, $validFrom, $validTo, $standard);
    }

    /**
     * The accountant edits a tax: its name, how it is computed, the rate, the accounts and the dates it is in force.
     * Its class and kind never change (they decide how documents post it).
     *
     * @throws TaxNotEditable        for "Ninguno"
     * @throws InvalidTaxDefinition  on the field at fault
     */
    public function revise(
        string $name,
        TaxCalculation $calculation,
        string $rate,
        ?Uuid $salesAccountId,
        ?Uuid $purchaseAccountId,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo,
    ): void {
        $this->assertEditable();
        $rate = self::checkedRate($this->kind, $this->taxClass, $calculation, $rate);
        self::checkDates($validFrom, $validTo);

        $this->name = trim($name);
        $this->calculation = $calculation;
        $this->rate = $rate;
        $this->salesAccountId = $salesAccountId;
        $this->purchaseAccountId = $purchaseAccountId;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
    }

    /** @throws TaxNotEditable for "Ninguno" */
    public function deactivate(): void
    {
        $this->assertEditable();
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    /** Whether the rate is in force on a day (both end dates included); no date means unbounded on that side. */
    public function isValidOn(\DateTimeImmutable $day): bool
    {
        $date = $day->format('Y-m-d');

        return (null === $this->validFrom || $this->validFrom->format('Y-m-d') <= $date)
            && (null === $this->validTo || $date <= $this->validTo->format('Y-m-d'));
    }

    /** "Ninguno" is the tax a line without a tax points at. */
    public function isNone(): bool
    {
        return TaxKind::None === $this->kind;
    }

    private function assertEditable(): void
    {
        if ($this->isNone()) {
            throw new TaxNotEditable();
        }
    }

    private static function kindBelongsTo(TaxClass $class, TaxKind $kind): bool
    {
        return TaxKind::None === $kind || match ($class) {
            TaxClass::Charge => \in_array($kind, [TaxKind::Vat, TaxKind::Consumption], true),
            TaxClass::Withholding => \in_array($kind, [TaxKind::IncomeWithholding, TaxKind::VatWithholding, TaxKind::IcaWithholding], true),
        };
    }

    private static function checkedRate(TaxKind $kind, TaxClass $class, TaxCalculation $calculation, string $rate): string
    {
        if (TaxCalculation::PerUnit === $calculation && !(TaxClass::Charge === $class && TaxKind::Consumption === $kind)) {
            throw new InvalidTaxDefinition('calculation', 'Only impoconsumo may be a value per unit.');
        }
        try {
            $value = TaxCalculation::PerUnit === $calculation ? UnitPrice::of($rate)->toBigDecimal() : Rate::of($rate)->toBigDecimal();
        } catch (\InvalidArgumentException|MathException) {
            throw new InvalidTaxDefinition('rate', TaxCalculation::PerUnit === $calculation ? 'The value must be zero or more, with at most four decimals.' : 'The rate must be between 0 and 100, with at most four decimals.');
        }
        if (TaxKind::None === $kind && !$value->isZero()) {
            throw new InvalidTaxDefinition('rate', 'A tax of kind "none" has no rate.');
        }

        return (string) $value;
    }

    private static function checkDates(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): void
    {
        if (null !== $from && null !== $to && $to->format('Y-m-d') < $from->format('Y-m-d')) {
            throw new InvalidTaxDefinition('valid_to', 'The end date cannot be before the start date.');
        }
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
