<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Attaches the supplier's PDF or XML to a draft (§4.10, §6); the caller checked its type and size. */
final readonly class AttachSupplierFile
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public Uuid $userId,
        public string $path,
        public string $originalName,
        public string $contentType,
    ) {
    }
}
