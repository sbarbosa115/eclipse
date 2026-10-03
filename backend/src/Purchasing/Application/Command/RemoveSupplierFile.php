<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Removes a file from a draft. */
final readonly class RemoveSupplierFile
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public Uuid $attachmentId,
    ) {
    }
}
