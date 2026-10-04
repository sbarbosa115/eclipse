<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\Quotation;
use App\Sales\Domain\Model\QuotationLine;

/** A cotización, whole: the header, its own fields, the lines, the totals, where it stands and its audit. */
final readonly class QuotationOutput
{
    /**
     * @param list<SalesInvoiceLineOutput> $lines
     */
    public function __construct(
        public string $id,
        /** draft, emitted, accepted, rejected, expired or voided: an emitted one past its vencimiento reads as expired */
        public string $status,
        /** As printed (C-12); null on a draft. */
        public ?string $number,
        public ?string $prefix,
        public ?int $sequence,
        public string $terceroId,
        public string $terceroName,
        public ?string $contactId,
        /** Responsable (a tercero with role empleado). */
        public ?string $responsibleId,
        /** "Nombre" of the responsable, for the form; null when there is none or it no longer exists. */
        public ?string $responsibleName,
        public string $issueDate,
        /** Fecha de vencimiento of the offer. */
        public string $expiryDate,
        /** Encabezado, plain text. */
        public ?string $header,
        /** Condiciones comerciales, plain text. */
        public ?string $terms,
        public ?string $notes,
        public string $grossTotal,
        public string $discountTotal,
        public string $subtotal,
        public string $taxTotal,
        public string $withholdingTotal,
        public string $netTotal,
        public array $lines,
        /** The draft invoice it was converted into (§4.7). */
        public ?string $convertedInvoiceId,
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
     */
    public static function of(Quotation $q, \DateTimeImmutable $today, array $productLabels, ?string $responsibleName): self
    {
        return new self(
            $q->id()->toRfc4122(),
            $q->statusOn($today)->value,
            $q->number(),
            $q->prefix(),
            $q->sequence(),
            $q->terceroId()->toRfc4122(),
            $q->terceroName(),
            $q->contactId()?->toRfc4122(),
            $q->responsibleId()?->toRfc4122(),
            $responsibleName,
            $q->issueDate()->format('Y-m-d'),
            $q->expiryDate()->format('Y-m-d'),
            $q->header(),
            $q->terms(),
            $q->notes(),
            $q->grossTotal()->toString(),
            $q->discountTotal()->toString(),
            $q->subtotal()->toString(),
            $q->taxTotal()->toString(),
            $q->withholdingTotal()->toString(),
            $q->netTotal()->toString(),
            array_map(static fn (QuotationLine $l) => SalesInvoiceLineOutput::of($l, null === $l->productId() ? null : ($productLabels[$l->productId()->toRfc4122()] ?? null)), $q->lines()),
            $q->convertedInvoiceId()?->toRfc4122(),
            $q->createdBy()->toRfc4122(),
            $q->createdAt()->format(\DATE_ATOM),
            $q->emittedBy()?->toRfc4122(),
            $q->emittedAt()?->format(\DATE_ATOM),
            $q->voidedBy()?->toRfc4122(),
            $q->voidedAt()?->format(\DATE_ATOM),
            $q->voidReason(),
        );
    }
}
