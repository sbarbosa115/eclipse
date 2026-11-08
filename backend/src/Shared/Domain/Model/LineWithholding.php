<?php

namespace App\Shared\Domain\Model;

use App\Shared\Domain\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One retención of a document line (ReteFuente, ReteIVA or ReteICA), as it was when the line was written: the tax's
 * snapshot, the base it was computed on (the line's subtotal, or its IVA for a ReteIVA) and its share of the
 * document's Retenciones. A line holds none or one per kind; their amounts add up to the line's withholding amount.
 * Each document's line table has its own table of these (`<line table>_withholding`).
 */
#[ORM\MappedSuperclass]
abstract class LineWithholding implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    protected Uuid $id;

    #[ORM\Column(type: 'money')]
    protected Money $baseAmount;

    #[ORM\Column(type: 'money')]
    protected Money $amount;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        protected Uuid $companyId,
        #[ORM\Column]
        protected int $position,
        #[ORM\Embedded(class: TaxSnapshot::class, columnPrefix: false)]
        protected TaxSnapshot $tax,
    ) {
        $this->id = Uuid::v7();
        $this->baseAmount = $this->amount = Money::zero();
    }

    public function applyAmounts(Money $base, Money $amount): void
    {
        $this->baseAmount = $base;
        $this->amount = $amount;
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

    public function tax(): TaxSnapshot
    {
        return $this->tax;
    }

    public function baseAmount(): Money
    {
        return $this->baseAmount;
    }

    public function amount(): Money
    {
        return $this->amount;
    }
}
