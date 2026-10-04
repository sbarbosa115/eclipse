<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** A new draft with the same client, lines and formas de pago, dated today (§4.15) → its id. */
final readonly class DuplicateSalesInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public Uuid $userId,
    ) {
    }
}
