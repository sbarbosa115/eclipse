<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Rewrites a draft cotización: header, lines and its own fields (§4.6: only drafts are editable). */
final readonly class UpdateDraftQuotation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $quotationId,
        public QuotationData $data,
    ) {
    }
}
