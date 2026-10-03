<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Rewrites a draft: header, lines and formas de pago (§4.6: only drafts are editable). */
final readonly class UpdateDraftSalesInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public SalesInvoiceData $data,
    ) {
    }
}
