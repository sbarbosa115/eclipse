<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** One forma de pago: the method, the amount (a decimal string) and, on crédito, the due date. */
final readonly class SalesInvoicePaymentData
{
    public function __construct(
        public Uuid $paymentMethodId,
        public string $amount,
        public ?\DateTimeImmutable $dueDate,
    ) {
    }
}
