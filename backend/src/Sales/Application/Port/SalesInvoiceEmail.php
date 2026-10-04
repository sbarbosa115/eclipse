<?php

namespace App\Sales\Application\Port;

/** The e-mail of an invoice: to whom, about what, and the PDF it carries. */
final readonly class SalesInvoiceEmail
{
    public function __construct(
        public string $to,
        public string $clientName,
        public string $companyName,
        public string $number,
        public string $netTotal,
        public string $pdf,
        public string $fileName,
    ) {
    }
}
