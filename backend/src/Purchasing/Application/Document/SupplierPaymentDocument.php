<?php

namespace App\Purchasing\Application\Document;

/** A payment's PDF and what an e-mail about it says. */
final readonly class SupplierPaymentDocument
{
    public function __construct(
        public string $bytes,
        public string $fileName,
        public string $number,
        public string $companyName,
        public string $terceroId,
        /** As printed: "$ 1.190.000,00". */
        public string $amount,
    ) {
    }
}
