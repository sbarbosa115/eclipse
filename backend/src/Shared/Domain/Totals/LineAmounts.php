<?php

namespace App\Shared\Domain\Totals;

use App\Shared\Domain\Money\Money;

/**
 * One line's share of the document totals, in cents. Across the lines each amount adds up exactly to the document's
 * (DocumentTotals), so a journal entry built from lines balances to the cent. $withholdings holds one share per
 * retención of the line, in the line's order; $withholding is their sum.
 */
final readonly class LineAmounts
{
    /**
     * @param list<Money> $withholdings
     */
    public function __construct(
        public Money $gross,
        public Money $discount,
        public Money $subtotal,
        public Money $tax,
        public Money $withholding,
        public Money $total,
        public array $withholdings = [],
    ) {
    }
}
