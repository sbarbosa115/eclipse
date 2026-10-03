<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/** A forma de pago row as a draft is given it: the method as it is now (name, kind, account), the amount, the due date. */
final readonly class InvoicePaymentDraft
{
    public function __construct(
        public Uuid $paymentMethodId,
        public string $methodName,
        public PaymentKind $kind,
        /** Contado: the method's account. Crédito: null (the tercero's receivable account). */
        public ?Uuid $accountId,
        public Money $amount,
        /** Crédito only. */
        public ?\DateTimeImmutable $dueDate,
    ) {
    }
}
