<?php

namespace App\Sales\Application\Port;

/** The e-mail of a quotation: to whom, about what, and the PDF it carries. */
final readonly class QuotationEmail
{
    public function __construct(
        public string $to,
        public string $clientName,
        public string $companyName,
        public string $number,
        public string $netTotal,
        public string $expiryDate,
        public string $pdf,
        public string $fileName,
    ) {
    }
}
