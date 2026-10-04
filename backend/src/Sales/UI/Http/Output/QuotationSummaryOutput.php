<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\Quotation;

/** A row of the cotizaciones list (§4.15): the number, the client, the dates, the total and where it stands. */
final readonly class QuotationSummaryOutput
{
    public function __construct(
        public string $id,
        /** draft, emitted, accepted, rejected, expired or voided: an emitted one past its vencimiento reads as expired */
        public string $status,
        /** Null on a draft. */
        public ?string $number,
        public string $issueDate,
        /** Fecha de vencimiento of the offer. */
        public string $expiryDate,
        public string $terceroId,
        public string $terceroName,
        public string $subtotal,
        public string $taxTotal,
        public string $withholdingTotal,
        public string $netTotal,
        /** The draft invoice it was converted into. */
        public ?string $convertedInvoiceId,
    ) {
    }

    public static function of(Quotation $q, \DateTimeImmutable $today): self
    {
        return new self(
            $q->id()->toRfc4122(),
            $q->statusOn($today)->value,
            $q->number(),
            $q->issueDate()->format('Y-m-d'),
            $q->expiryDate()->format('Y-m-d'),
            $q->terceroId()->toRfc4122(),
            $q->terceroName(),
            $q->subtotal()->toString(),
            $q->taxTotal()->toString(),
            $q->withholdingTotal()->toString(),
            $q->netTotal()->toString(),
            $q->convertedInvoiceId()?->toRfc4122(),
        );
    }
}
