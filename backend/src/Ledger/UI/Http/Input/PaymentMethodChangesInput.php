<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Edit a payment method: its kind never changes. */
final class PaymentMethodChangesInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    public string $name = '';

    #[Assert\Uuid]
    public ?string $accountId = null;
}
