<?php

namespace App\Purchasing\Application\Port;

/** The e-mail of a purchase order: to whom, about what, and the PDF it carries. */
final readonly class PurchaseOrderEmail
{
    public function __construct(
        public string $to,
        public string $supplierName,
        public string $companyName,
        public string $number,
        public string $netTotal,
        public string $expectedDate,
        public string $pdf,
        public string $fileName,
    ) {
    }
}
