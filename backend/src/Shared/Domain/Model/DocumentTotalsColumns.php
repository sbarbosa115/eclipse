<?php

namespace App\Shared\Domain\Model;

use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Totals\DocumentTotals;
use Doctrine\ORM\Mapping as ORM;

/**
 * The six totals of a commercial document (§4.6), kept on its row so lists and cartera never recompute them.
 */
trait DocumentTotalsColumns
{
    #[ORM\Column(type: 'money')]
    private Money $grossTotal;

    #[ORM\Column(type: 'money')]
    private Money $discountTotal;

    #[ORM\Column(type: 'money')]
    private Money $subtotal;

    #[ORM\Column(type: 'money')]
    private Money $taxTotal;

    #[ORM\Column(type: 'money')]
    private Money $withholdingTotal;

    #[ORM\Column(type: 'money')]
    private Money $netTotal;

    private function zeroTotals(): void
    {
        $this->grossTotal = $this->discountTotal = $this->subtotal = $this->taxTotal = $this->withholdingTotal = $this->netTotal = Money::zero();
    }

    /**
     * @param iterable<CommercialLine> $lines in position order
     */
    private function recomputeTotals(iterable $lines): DocumentTotals
    {
        $list = [];
        foreach ($lines as $line) {
            $list[] = $line;
        }
        $totals = DocumentTotals::of(array_map(static fn (CommercialLine $l) => $l->toLineInput(), $list));
        foreach ($list as $i => $line) {
            $line->applyAmounts($totals->lines[$i]);
        }
        $this->grossTotal = $totals->gross;
        $this->discountTotal = $totals->discounts;
        $this->subtotal = $totals->subtotal;
        $this->taxTotal = $totals->taxes;
        $this->withholdingTotal = $totals->withholdings;
        $this->netTotal = $totals->net;

        return $totals;
    }

    public function grossTotal(): Money
    {
        return $this->grossTotal;
    }

    public function discountTotal(): Money
    {
        return $this->discountTotal;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function taxTotal(): Money
    {
        return $this->taxTotal;
    }

    public function withholdingTotal(): Money
    {
        return $this->withholdingTotal;
    }

    public function netTotal(): Money
    {
        return $this->netTotal;
    }
}
