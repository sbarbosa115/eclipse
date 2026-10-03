<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** E-mails an emitted invoice's PDF to the client's billing address, through the queue (§4.15). */
final readonly class SendSalesInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
    ) {
    }
}
