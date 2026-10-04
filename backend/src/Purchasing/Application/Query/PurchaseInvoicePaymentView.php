<?php

namespace App\Purchasing\Application\Query;

final readonly class PurchaseInvoicePaymentView
{
    public function __construct(
        public string $id,
        public int $position,
        public string $paymentMethodId,
        public string $methodName,
        /** cash or credit */
        public string $kind,
        public string $amount,
        public ?string $dueDate,
    ) {
    }
}
