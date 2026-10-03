<?php

namespace App\Company\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** The invoicing resolution (§4.1): the DIAN's authorisation. Dates are "Y-m-d". */
final class ResolutionInput
{
    #[Assert\NotBlank(message: 'Write the resolution number.')]
    #[Assert\Length(max: 40)]
    public string $resolutionNumber = '';

    #[Assert\Length(max: 10)]
    public string $prefix = '';

    #[Assert\Range(min: 1, max: 999999999, notInRangeMessage: 'Numbers go from 1 to 999999999.')]
    public int $rangeFrom = 1;

    #[Assert\Range(min: 1, max: 999999999, notInRangeMessage: 'Numbers go from 1 to 999999999.')]
    public int $rangeTo = 1;

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $validFrom = '';

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $validTo = '';

    #[Assert\Choice(choices: ['electronic', 'manual'])]
    public string $mode = 'electronic';
}
