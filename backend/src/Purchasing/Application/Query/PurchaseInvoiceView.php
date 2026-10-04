<?php

namespace App\Purchasing\Application\Query;

use App\Purchasing\Application\Port\SupplierFileView;

/** A purchase invoice with everything the editor, the detail and the PDF show. */
final readonly class PurchaseInvoiceView
{
    /**
     * @param list<PurchaseInvoiceLineView>    $lines
     * @param list<PurchaseInvoicePaymentView> $payments
     * @param list<PayableView>                $payables
     * @param list<SupplierFileView>           $attachments
     */
    public function __construct(
        public string $id,
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
}
