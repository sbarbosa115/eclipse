<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class LockDateInput
{
    /** YYYY-MM-DD: nothing may be emitted or voided on or before it. */
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $lockedUntil = '';
}
