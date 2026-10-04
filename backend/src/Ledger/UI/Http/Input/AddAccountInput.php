<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class AddAccountInput
{
    /** The cuenta, subcuenta or auxiliar it goes under. */
    #[Assert\NotBlank]
    #[Assert\Length(max: 14)]
    public string $parentCode = '';

    /** The parent's code plus two digits. */
    #[Assert\NotBlank]
    #[Assert\Length(max: 16)]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $name = '';

    /** Null: classes 5, 6 and 7 are usable on purchase lines, the rest are not. */
    public ?bool $usableOnPurchases = null;
}
