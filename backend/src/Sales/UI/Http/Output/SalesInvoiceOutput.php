<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\Receivable;
use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Domain\Model\SalesInvoiceLine;
use App\Sales\Domain\Model\SalesInvoicePayment;

/** A factura de venta, whole: the header, the numbers, the lines, the formas de pago, the totals, its receivables and its audit. */
final readonly class SalesInvoiceOutput
{
    /**
     * @param list<SalesInvoiceLineOutput>    $lines
     * @param list<SalesInvoicePaymentOutput> $payments
     * @param list<ReceivableOutput>          $receivables
     */
    public function __construct(
        public string $id,
        /** draft, emitted, partially_paid, paid or voided */
        public string $status,
        /** As printed (prefix-authorised number); null on a draft. */
        public ?string $number,
        public ?string $prefix,
        /** The resolution's consecutive. */
        public ?int $authorisedNumber,
        /** The company's internal consecutive. */
        public ?int $internalNumber,
        public ?string $resolutionId,
        public string $terceroId,
        public string $terceroName,
        public ?string $contactId,
        public ?string $sellerId,
        /** The cotización it was converted from. */
        public ?string $quotationId,
        public string $issueDate,
        public ?string $notes,
        public string $grossTotal,
        public string $discountTotal,
        public string $subtotal,
        public string $taxTotal,
        public string $withholdingTotal,
        public string $netTotal,
        public string $paidAmount,
        public string $balance,
        public array $lines,
        public array $payments,
        public array $receivables,
        public ?string $journalEntryId,
        public ?string $reversalEntryId,
        public string $createdBy,
        public string $createdAt,
        public ?string $emittedBy,
        public ?string $emittedAt,
        public ?string $voidedBy,
        public ?string $voidedAt,
        public ?string $voidReason,
    ) {
    }

    /**
     * @param array<string, string> $productLabels "código · nombre" by product id
     * @param list<Receivable>      $receivables
     */
    public static function of(SalesInvoice $i, array $productLabels, array $receivables): self
    {
        return new self(
            $i->id()->toRfc4122(),
            $i->status()->value,
            $i->number(),
            $i->prefix(),
            $i->authorisedNumber(),
            $i->internalNumber(),
            $i->resolutionId()?->toRfc4122(),
            $i->terceroId()->toRfc4122(),
            $i->terceroName(),
            $i->contactId()?->toRfc4122(),
            $i->sellerId()?->toRfc4122(),
            $i->quotationId()?->toRfc4122(),
            $i->issueDate()->format('Y-m-d'),
            $i->notes(),
            $i->grossTotal()->toString(),
            $i->discountTotal()->toString(),
            $i->subtotal()->toString(),
            $i->taxTotal()->toString(),
            $i->withholdingTotal()->toString(),
            $i->netTotal()->toString(),
            $i->paidAmount()->toString(),
            $i->balance()->toString(),
            array_map(static fn (SalesInvoiceLine $l) => SalesInvoiceLineOutput::of($l, null === $l->productId() ? null : ($productLabels[$l->productId()->toRfc4122()] ?? null)), $i->lines()),
            array_map(SalesInvoicePaymentOutput::of(...), $i->payments()),
            array_map(ReceivableOutput::of(...), $receivables),
            $i->journalEntryId()?->toRfc4122(),
            $i->reversalEntryId()?->toRfc4122(),
            $i->createdBy()->toRfc4122(),
            $i->createdAt()->format(\DATE_ATOM),
            $i->emittedBy()?->toRfc4122(),
            $i->emittedAt()?->format(\DATE_ATOM),
            $i->voidedBy()?->toRfc4122(),
            $i->voidedAt()?->format(\DATE_ATOM),
            $i->voidReason(),
        );
    }
}
