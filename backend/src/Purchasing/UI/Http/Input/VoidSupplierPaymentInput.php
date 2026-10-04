<?php

namespace App\Purchasing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Why the payment is voided (§4.12: recorded with who and when). */
final class VoidSupplierPaymentInput
{
    #[Assert\NotBlank(message: 'Write the reason for the void.', normalizer: 'trim')]
    #[Assert\Length(max: 500)]
    public string $reason = '';
}
