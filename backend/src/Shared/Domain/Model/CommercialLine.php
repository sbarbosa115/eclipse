<?php

namespace App\Shared\Domain\Model;

use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\LineAmounts;
use App\Shared\Domain\Totals\LineInput;
use App\Shared\Domain\Totals\TaxBase;
use App\Shared\Domain\Totals\TaxCalculation;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A line of a cotización, factura de venta or factura de compra (§4.6): a catalog item or, on purchases, an account
 * chosen directly; its quantity, price and discount; its impuesto cargo and its retenciones (none, or one per kind) as
 * they were when the line was written (name, calculation, rate and the accounts they post to), so editing a tax never
 * changes a document; and the product's code as it was then. The amounts are its share of the document totals, set by
 * the document each time its lines change (DocumentTotals).
 *
 * The retenciones live in each concrete line's own collection (a mapped superclass cannot hold the inverse side of an
 * association): a subclass builds them in its constructor with addWithholdings() and lists them in withholdings().
 */
#[ORM\MappedSuperclass]
abstract class CommercialLine implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    protected Uuid $id;

    #[ORM\Column(type: 'money')]
    protected Money $grossAmount;

    #[ORM\Column(type: 'money')]
    protected Money $discountAmount;

    #[ORM\Column(type: 'money')]
    protected Money $subtotalAmount;

    #[ORM\Column(type: 'money')]
    protected Money $taxAmount;

    #[ORM\Column(type: 'money')]
    protected Money $withholdingAmount;

    #[ORM\Column(type: 'money')]
    protected Money $totalAmount;

    /** The product's código when the line was written (null on a line by account, §4.4 of the observations PRD). */
    #[ORM\Column(length: 40, nullable: true)]
    protected ?string $productCode = null;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        protected Uuid $companyId,
        #[ORM\Column]
        protected int $position,
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('product')]
        protected ?Uuid $productId,
        /** Purchases only: an expense or cost account chosen instead of a product (§4.10). */
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('ledger_account')]
        protected ?Uuid $accountId,
        #[ORM\Column(length: 500)]
        protected string $description,
        #[ORM\Column(type: 'quantity')]
        protected Quantity $quantity,
        #[ORM\Column(type: 'unit_price')]
        protected UnitPrice $unitPrice,
        #[ORM\Column(type: 'rate')]
        protected Rate $discount,
        #[ORM\Embedded(class: TaxSnapshot::class, columnPrefix: 'charge_')]
        protected TaxSnapshot $chargeTax,
    ) {
        $this->id = Uuid::v7();
        $this->grossAmount = $this->discountAmount = $this->subtotalAmount = $this->taxAmount = $this->withholdingAmount = $this->totalAmount = Money::zero();
    }

    /**
     * The line's retenciones, in order.
     *
     * @return list<LineWithholding>
     */
    abstract public function withholdings(): array;

    /** Builds the line's retención entity for a snapshot (the subclass's own LineWithholding). */
    abstract protected function newWithholding(int $position, TaxSnapshot $tax): LineWithholding;

    /** Adds the line's retención entity to the subclass's collection. */
    abstract protected function addWithholding(LineWithholding $withholding): void;

    /**
     * Called by a subclass's constructor, after its collection exists. A snapshot of no tax is skipped.
     *
     * @param list<TaxSnapshot> $withholdings
     */
    protected function addWithholdings(array $withholdings): void
    {
        $position = 0;
        foreach ($withholdings as $tax) {
            if (!$tax->isNone()) {
                $this->addWithholding($this->newWithholding(++$position, $tax));
            }
        }
    }

    /**
     * The snapshots of the line's retenciones, in order: what a copy (duplicate, convert, correct) passes on.
     *
     * @return list<TaxSnapshot>
     */
    public function withholdingTaxes(): array
    {
        return array_map(static fn (LineWithholding $w) => $w->tax(), $this->withholdings());
    }

    /**
     * Deprecated: the line's first retención, or "no tax". Kept while the single-retención API alias lives
     * (`withholding_tax_id`, observations PRD §6).
     */
    public function withholdingTax(): TaxSnapshot
    {
        return ($this->withholdings()[0] ?? null)?->tax() ?? TaxSnapshot::none();
    }

    public function toLineInput(): LineInput
    {
        return new LineInput(
            $this->quantity,
            $this->unitPrice,
            $this->discount,
            $this->chargeTax->rate(),
            array_map(static fn (LineWithholding $w) => $w->tax()->rate(), $this->withholdings()),
        );
    }

    public function applyAmounts(LineAmounts $amounts): void
    {
        $this->grossAmount = $amounts->gross;
        $this->discountAmount = $amounts->discount;
        $this->subtotalAmount = $amounts->subtotal;
        $this->taxAmount = $amounts->tax;
        $this->withholdingAmount = $amounts->withholding;
        $this->totalAmount = $amounts->total;
        foreach ($this->withholdings() as $k => $withholding) {
            $base = TaxBase::ChargeTax === $withholding->tax()->rate()->base ? $amounts->tax : $amounts->subtotal;
            $withholding->applyAmounts($base, $amounts->withholdings[$k] ?? Money::zero());
        }
    }

    /** Records the product's código on the line (documents copy what they use). */
    public function useProductCode(?string $code): void
    {
        $this->productCode = $code;
    }

    public function productCode(): ?string
    {
        return $this->productCode;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function productId(): ?Uuid
    {
        return $this->productId;
    }

    public function accountId(): ?Uuid
    {
        return $this->accountId;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function unitPrice(): UnitPrice
    {
        return $this->unitPrice;
    }

    public function discount(): Rate
    {
        return $this->discount;
    }

    public function chargeTax(): TaxSnapshot
    {
        return $this->chargeTax;
    }

    public function grossAmount(): Money
    {
        return $this->grossAmount;
    }

    public function discountAmount(): Money
    {
        return $this->discountAmount;
    }

    public function subtotalAmount(): Money
    {
        return $this->subtotalAmount;
    }

    public function taxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function withholdingAmount(): Money
    {
        return $this->withholdingAmount;
    }

    public function totalAmount(): Money
    {
        return $this->totalAmount;
    }

    /** Whether the calculation is per unit, for display. */
    public function chargeIsPerUnit(): bool
    {
        return TaxCalculation::PerUnit === $this->chargeTax->calculation();
    }
}
