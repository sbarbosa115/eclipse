<?php

namespace App\Company\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** When to warn that the resolution is running out. */
final class ResolutionWarningsInput
{
    #[Assert\Range(min: 0, max: 999999)]
    public int $warningNumbers = 100;

    #[Assert\Range(min: 0, max: 3650)]
    public int $warningDays = 30;
}
