<?php

namespace App\Sales\Application\Document;

/** A quotation's PDF and what an e-mail about it says. */
final readonly class QuotationDocument
{
    public function __construct(
        public string $bytes,
        public string $fileName,
        public string $number,
        public string $companyName,
        public string $terceroId,
        /** As printed: "$ 1.190.000,00". */
        public string $netTotal,
        /** As printed: "02/11/2026". */
        public string $expiryDate,
    ) {
    }
}
