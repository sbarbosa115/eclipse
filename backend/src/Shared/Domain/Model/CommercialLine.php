<?php

namespace App\Shared\Domain\Model;

use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\LineAmounts;
use App\Shared\Domain\Totals\LineInput;
use App\Shared\Domain\Totals\TaxCalculation;
use App\Shared\Domain\Totals\TaxRate;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A line of a cotización, factura de venta or factura de compra (§4.6): a catalog item or, on purchases, an account
 * chosen directly; its quantity, price and discount; and its two taxes as they were when the line was written (name,
 * calculation, rate and the accounts they post to), so editing a tax never changes a document. The amounts are its
 * share of the document totals, set by the document each time its lines change (DocumentTotals).
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
        #[ORM\Embedded(class: TaxSnapshot::class, columnPrefix: 'withholding_')]
        protected TaxSnapshot $withholdingTax,
    ) {
        $this->id = Uuid::v7();
        $this->grossAmount = $this->discountAmount = $this->subtotalAmount = $this->taxAmount = $this->withholdingAmount = $this->totalAmount = Money::zero();
    }

    public function toLineInput(): LineInput
    {
        return new LineInput($this->quantity, $this->unitPrice, $this->discount, $this->chargeTax->rate(), $this->withholdingTax->rate());
    }

    public function applyAmounts(LineAmounts $amounts): void
    {
        $this->grossAmount = $amounts->gross;
        $this->discountAmount = $amounts->discount;
        $this->subtotalAmount = $amounts->subtotal;
        $this->taxAmount = $amounts->tax;
        $this->withholdingAmount = $amounts->withholding;
        $this->totalAmount = $amounts->total;
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

    public function withholdingTax(): TaxSnapshot
    {
        return $this->withholdingTax;
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
