<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class PurchasePaymentContents
{
    public function __construct(
        public Uuid $paymentMethodId,
        /** Pesos, two decimals at most. */
        public string $amount,
        /** Crédito only; the invoice's due date when missing. */
        public ?\DateTimeImmutable $dueDate,
    ) {
    }
}
