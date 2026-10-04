<?php

namespace App\Sales\Domain\Event;

/** Someone asked to e-mail the quotation's PDF to the client: sent once the command has committed. */
final readonly class QuotationEmailRequested
{
    public function __construct(
        public string $companyId,
        public string $quotationId,
    ) {
    }
}
