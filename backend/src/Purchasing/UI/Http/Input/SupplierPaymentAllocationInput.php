<?php

namespace App\Purchasing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** What of the amount goes to one open payable of the supplier. */
final class SupplierPaymentAllocationInput
{
    #[Assert\NotBlank(message: 'Choose an open invoice of this supplier, once.')]
    #[Assert\Uuid(message: 'Choose an open invoice of this supplier, once.')]
    public string $payableId = '';

    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,2})?$/', message: 'Write the amount in pesos, with at most two decimals.')]
    #[Assert\NotBlank(message: 'Write the amount in pesos, with at most two decimals.')]
    public string $amount = '';
}
