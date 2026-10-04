<?php

namespace App\Company\Application\Query;

/** One internal series: its prefix and the number the next document takes. */
final readonly class NumberingSeriesView
{
    public function __construct(
        /** quotation, cash_receipt, purchase_invoice, supplier_payment, sales_invoice_internal */
        public string $kind,
        public string $prefix,
        public int $nextNumber,
    ) {
    }
}
