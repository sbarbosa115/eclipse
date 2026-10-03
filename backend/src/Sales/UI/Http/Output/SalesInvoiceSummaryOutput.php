<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Domain\Model\SalesInvoicePayment;

/** A row of the facturas de venta list (§4.15): the number, the client, the date, the totals and what is still owed. */
final readonly class SalesInvoiceSummaryOutput
{
    public function __construct(
        public string $id,
        /** draft, emitted, partially_paid, paid or voided */
        public string $status,
        /** Null on a draft. */
        public ?string $number,
        public string $issueDate,
        /** The latest crédito due date, or null when nothing is on crédito. */
        public ?string $dueDate,
        public string $terceroId,
        public string $terceroName,
        public string $subtotal,
        public string $taxTotal,
        public string $withholdingTotal,
        public string $netTotal,
        public string $paidAmount,
        /** What the client still owes: nothing on a draft or a voided invoice. */
        public string $balance,
    ) {
    }

    public static function of(SalesInvoice $i): self
    {
        $dues = array_filter(array_map(static fn (SalesInvoicePayment $p) => $p->dueDate()?->format('Y-m-d'), $i->payments()));

        return new self(
            $i->id()->toRfc4122(),
            $i->status()->value,
            $i->number(),
            $i->issueDate()->format('Y-m-d'),
            [] === $dues ? null : max($dues),
            $i->terceroId()->toRfc4122(),
            $i->terceroName(),
            $i->subtotal()->toString(),
            $i->taxTotal()->toString(),
            $i->withholdingTotal()->toString(),
            $i->netTotal()->toString(),
            $i->paidAmount()->toString(),
            $i->balance()->toString(),
        );
    }
}
