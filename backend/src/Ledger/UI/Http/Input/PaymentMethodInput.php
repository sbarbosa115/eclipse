<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Create a payment method: contado ("cash") with its account, or crédito ("credit") without one. */
final class PaymentMethodInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    public string $name = '';

    #[Assert\Choice(choices: ['cash', 'credit'])]
    public string $kind = 'cash';

    #[Assert\Uuid]
    public ?string $accountId = null;
}
