<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Corregir an emitted invoice nothing is paid on: void it and open an editable copy (observations PRD §4.6). */
final readonly class CorrectPurchaseInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public Uuid $userId,
        public string $reason,
    ) {
    }
}
