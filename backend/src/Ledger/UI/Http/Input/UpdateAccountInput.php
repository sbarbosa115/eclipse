<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAccountInput
{
    /** A PUC account keeps its name: send it unchanged. */
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $name = '';

    public bool $active = true;

    public bool $usableOnPurchases = false;
}
