<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\CashReceiptAllocation;

/** What the receipt paid of one receivable, and of which invoice. */
final readonly class CashReceiptAllocationOutput
{
    public function __construct(
        public string $id,
        public string $receivableId,
        public string $invoiceId,
        public string $invoiceNumber,
        public string $amount,
    ) {
    }

    public static function of(CashReceiptAllocation $a): self
    {
        return new self($a->id()->toRfc4122(), $a->openItemId()->toRfc4122(), $a->invoiceId()->toRfc4122(), $a->invoiceNumber(), $a->amount()->toString());
    }
}
