<?php

namespace App\Purchasing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** A forma de pago: the method, the amount and, on crédito, the due date (the invoice's when missing). */
final class PurchasePaymentInput
{
    #[Assert\NotBlank(message: 'Choose a payment method.')]
    #[Assert\Uuid]
    public string $paymentMethodId = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,16}(\.\d{1,2})?$/', message: 'Write the amount in pesos, with at most two decimals.')]
    #[Assert\Regex(pattern: '/^0+(\.0+)?$/', match: false, message: 'Write the amount in pesos, with at most two decimals.')]
    public string $amount = '';

    #[Assert\Date]
    public ?string $dueDate = null;
}
