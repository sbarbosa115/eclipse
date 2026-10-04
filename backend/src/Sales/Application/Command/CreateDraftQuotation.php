<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** A new draft cotización → its id. */
final readonly class CreateDraftQuotation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public QuotationData $data,
    ) {
    }
}
