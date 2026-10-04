<?php

namespace App\Company\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class NumberingSeriesInput
{
    #[Assert\Length(max: 10, maxMessage: 'The prefix has up to ten letters and digits.')]
    public string $prefix = '';

    #[Assert\Range(min: 1, max: 999999999, notInRangeMessage: 'Numbers go from 1 to 999999999.')]
    public int $nextNumber = 1;
}
