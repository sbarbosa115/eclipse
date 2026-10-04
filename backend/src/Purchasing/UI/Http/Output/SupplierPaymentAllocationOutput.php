<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Domain\Model\SupplierPaymentAllocation;

/** What the payment paid of one payable, and of which invoice. */
final readonly class SupplierPaymentAllocationOutput
{
    public function __construct(
        public string $id,
        public string $payableId,
        public string $invoiceId,
        public string $invoiceNumber,
        public string $amount,
    ) {
    }

    public static function of(SupplierPaymentAllocation $a): self
    {
        return new self($a->id()->toRfc4122(), $a->openItemId()->toRfc4122(), $a->invoiceId()->toRfc4122(), $a->invoiceNumber(), $a->amount()->toString());
    }
}
