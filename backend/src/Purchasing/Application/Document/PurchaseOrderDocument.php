<?php

namespace App\Purchasing\Application\Document;

/** An order's PDF and what an e-mail about it says. */
final readonly class PurchaseOrderDocument
{
    public function __construct(
        public string $bytes,
        public string $fileName,
        public string $number,
        public string $companyName,
        public string $terceroId,
        /** As printed: "$ 1.190.000,00". */
        public string $netTotal,
        /** As printed: "02/11/2026"; empty when the order has no expected date. */
        public string $expectedDate,
    ) {
    }
}
