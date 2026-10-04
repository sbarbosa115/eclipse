<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** "Duplicar" (§4.15): a new draft with the same client, lines and texts. */
final readonly class DuplicateQuotation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $quotationId,
        public Uuid $userId,
    ) {
    }
}
