<?php

namespace App\Sales\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class SalesInvoicePaymentInput
{
    #[Assert\NotBlank(message: 'Choose the payment method.')]
    #[Assert\Uuid]
    public string $paymentMethodId = '';

    /** Pesos, up to two decimals. */
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,2})?$/', message: 'Write the amount in pesos, with at most two decimals.')]
    public string $amount = '';

    /** Crédito only: fecha de vencimiento, YYYY-MM-DD. */
    #[Assert\Date]
    public ?string $dueDate = null;
}
