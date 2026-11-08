<?php

namespace App\Purchasing\Application\Query;

/** A row of the purchase orders list. */
final readonly class PurchaseOrderSummary
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $number,
        public string $terceroId,
        public string $terceroName,
        public string $issueDate,
        public ?string $expectedDate,
        public string $netTotal,
        public ?string $convertedInvoiceId,
    ) {
    }
}
