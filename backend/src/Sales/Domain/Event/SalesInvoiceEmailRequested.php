<?php

namespace App\Sales\Domain\Event;

/** Someone asked to e-mail the invoice's PDF to the client: sent once the command has committed. */
final readonly class SalesInvoiceEmailRequested
{
    public function __construct(
        public string $companyId,
        public string $invoiceId,
    ) {
    }
}
