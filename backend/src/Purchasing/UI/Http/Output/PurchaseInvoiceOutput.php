<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Application\Query\PurchaseInvoiceView;

/** A factura de compra with its lines, formas de pago, payables and the supplier's files. */
final readonly class PurchaseInvoiceOutput
{
    /**
     * @param list<PurchaseInvoiceLineOutput>       $lines
     * @param list<PurchaseInvoicePaymentOutput>    $payments
     * @param list<PurchasePayableOutput>           $payables
     * @param list<PurchaseInvoiceAttachmentOutput> $attachments
     */
    public function __construct(
        public string $id,
        /** draft, emitted, partially_paid, paid, voided */
        public string $status,
        public ?string $number,
        public string $terceroId,
        public string $terceroName,
        public ?string $supplierInvoiceNumber,
        public string $issueDate,
        public ?string $dueDate,
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
        public array $payables,
        public array $attachments,
        public ?string $journalEntryId,
        public ?string $reversalEntryId,
        public string $createdAt,
        public ?string $emittedAt,
        public ?string $voidedAt,
        public ?string $voidReason,
    ) {
    }

    public static function of(PurchaseInvoiceView $v): self
    {
        return new self(
            $v->id, $v->status, $v->number, $v->terceroId, $v->terceroName, $v->supplierInvoiceNumber, $v->issueDate, $v->dueDate, $v->notes,
            $v->grossTotal, $v->discountTotal, $v->subtotal, $v->taxTotal, $v->withholdingTotal, $v->netTotal, $v->paidAmount, $v->balance,
            array_map(PurchaseInvoiceLineOutput::of(...), $v->lines),
            array_map(PurchaseInvoicePaymentOutput::of(...), $v->payments),
            array_map(PurchasePayableOutput::of(...), $v->payables),
            array_map(PurchaseInvoiceAttachmentOutput::of(...), $v->attachments),
            $v->journalEntryId, $v->reversalEntryId, $v->createdAt, $v->emittedAt, $v->voidedAt, $v->voidReason,
        );
    }
}
