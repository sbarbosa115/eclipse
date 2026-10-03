<?php

namespace App\Company\UI\Http\Output;

use App\Company\Application\Query\NumberingSeriesView;

/** One internal numbering series. */
final readonly class NumberingSeriesOutput
{
    public function __construct(
        /** quotation, cash_receipt, purchase_invoice, supplier_payment, sales_invoice_internal */
        public string $kind,
        public string $prefix,
        /** The number the next document takes. */
        public int $nextNumber,
    ) {
    }

    public static function of(NumberingSeriesView $v): self
    {
        return new self($v->kind, $v->prefix, $v->nextNumber);
    }
}
