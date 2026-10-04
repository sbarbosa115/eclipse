<?php

namespace App\Sales\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** What of the amount goes to one open receivable of the client. */
final class CashReceiptAllocationInput
{
    #[Assert\NotBlank(message: 'Choose an open invoice of this client, once.')]
    #[Assert\Uuid(message: 'Choose an open invoice of this client, once.')]
    public string $receivableId = '';

    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,2})?$/', message: 'Write the amount in pesos, with at most two decimals.')]
    #[Assert\NotBlank(message: 'Write the amount in pesos, with at most two decimals.')]
    public string $amount = '';
}
