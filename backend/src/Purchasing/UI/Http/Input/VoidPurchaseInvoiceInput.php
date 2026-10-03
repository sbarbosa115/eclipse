<?php

namespace App\Purchasing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Why the invoice is voided (§4.12): kept with it. */
final class VoidPurchaseInvoiceInput
{
    #[Assert\NotBlank(message: 'Write why the invoice is voided.', normalizer: 'trim')]
    #[Assert\Length(max: 500)]
    public string $reason = '';
}
