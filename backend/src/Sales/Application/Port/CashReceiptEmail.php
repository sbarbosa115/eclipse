<?php

namespace App\Sales\Application\Port;

/** The e-mail of a recibo de caja: to whom, about what, and the PDF it carries. */
final readonly class CashReceiptEmail
{
    public function __construct(
        public string $to,
        public string $clientName,
        public string $companyName,
        public string $number,
        public string $amount,
        public string $pdf,
        public string $fileName,
    ) {
    }
}
