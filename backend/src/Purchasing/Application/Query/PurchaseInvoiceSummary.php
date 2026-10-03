<?php

namespace App\Purchasing\Application\Query;

/** A row of the list. */
final readonly class PurchaseInvoiceSummary
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $number,
        public string $terceroId,
        public string $terceroName,
        public ?string $supplierInvoiceNumber,
        public string $issueDate,
        public ?string $dueDate,
        public string $netTotal,
        public string $paidAmount,
        public string $balance,
    ) {
    }
}
