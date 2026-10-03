<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A forma de pago of a draft, the method copied as it is now: contado with the account its money leaves from, crédito
 * with the date the payable falls due.
 */
final readonly class PurchasePaymentDraft
{
    public function __construct(
        public Uuid $paymentMethodId,
        public string $methodName,
        public PaymentKind $kind,
        public ?Uuid $accountId,
        public Money $amount,
        public ?\DateTimeImmutable $dueDate,
    ) {
    }
}
