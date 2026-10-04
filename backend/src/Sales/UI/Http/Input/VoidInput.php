<?php

namespace App\Sales\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Why the invoice is voided (§4.12: recorded with who and when). */
final class VoidInput
{
    #[Assert\NotBlank(message: 'Write the reason for the void.')]
    #[Assert\Length(max: 500)]
    public string $reason = '';
}
