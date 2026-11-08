<?php

namespace App\Purchasing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Why the invoice is corrected (observations PRD §4.6): kept with the voided one as "Corregida: <reason>". */
final class CorrectPurchaseInvoiceInput
{
    /** The void reason holds 500 characters, "Corregida: " included. */
    public const MAX_LENGTH = 489;

    #[Assert\NotBlank(message: 'Write why the invoice is corrected.', normalizer: 'trim')]
    #[Assert\Length(max: self::MAX_LENGTH)]
    public string $reason = '';
}
