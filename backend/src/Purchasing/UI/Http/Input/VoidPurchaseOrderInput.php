<?php

namespace App\Purchasing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Why the orden de compra is voided: kept with it. */
final class VoidPurchaseOrderInput
{
    #[Assert\NotBlank(message: 'Write why the order is voided.', normalizer: 'trim')]
    #[Assert\Length(max: 500)]
    public string $reason = '';
}
