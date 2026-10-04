<?php

namespace App\Purchasing\Application\Query;

/** A payable as cartera de proveedores and the supplier payment read it. Money as decimal strings, dates Y-m-d. */
final readonly class PayableView
{
    public function __construct(
        public string $id,
        public string $invoiceId,
        public string $invoiceNumber,
        public string $terceroId,
        public string $issueDate,
        public string $dueDate,
        public string $amount,
        public string $balance,
        public bool $voided,
    ) {
    }
}
