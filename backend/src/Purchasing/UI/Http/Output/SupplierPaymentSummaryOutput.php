<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Domain\Model\SupplierPayment;
use App\Purchasing\Domain\Model\SupplierPaymentAllocation;

/** A row of the recibos de pago list (§4.15): the number, date, supplier, method, amount and the invoices it paid. */
final readonly class SupplierPaymentSummaryOutput
{
    /**
     * @param list<string> $invoiceNumbers
     */
    public function __construct(
        public string $id,
        /** emitted or voided */
        public string $status,
        public string $number,
        public string $receiptDate,
        public string $terceroId,
        public string $terceroName,
        public string $methodName,
        public string $amount,
        public array $invoiceNumbers,
    ) {
    }

    public static function of(SupplierPayment $p): self
    {
        return new self(
            $p->id()->toRfc4122(),
            $p->status()->value,
            $p->number(),
            $p->receiptDate()->format('Y-m-d'),
            $p->terceroId()->toRfc4122(),
            $p->terceroName(),
            $p->methodName(),
            $p->amount()->toString(),
            array_map(static fn (SupplierPaymentAllocation $a) => $a->invoiceNumber(), $p->allocations()),
        );
    }
}
