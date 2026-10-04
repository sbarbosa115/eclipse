<?php

namespace App\Purchasing\Application\Port;

/** The e-mail of a recibo de pago: to whom, about what, and the PDF it carries. */
final readonly class SupplierPaymentEmail
{
    public function __construct(
        public string $to,
        public string $supplierName,
        public string $companyName,
        public string $number,
        public string $amount,
        public string $pdf,
        public string $fileName,
    ) {
    }
}
