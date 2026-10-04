<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\CashReceipt;
use App\Sales\Domain\Model\CashReceiptAllocation;

/** A row of the recibos de caja list (§4.15): the number, date, client, method, amount and the invoices it paid. */
final readonly class CashReceiptSummaryOutput
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

    public static function of(CashReceipt $r): self
    {
        return new self(
            $r->id()->toRfc4122(),
            $r->status()->value,
            $r->number(),
            $r->receiptDate()->format('Y-m-d'),
            $r->terceroId()->toRfc4122(),
            $r->terceroName(),
            $r->methodName(),
            $r->amount()->toString(),
            array_map(static fn (CashReceiptAllocation $a) => $a->invoiceNumber(), $r->allocations()),
        );
    }
}
