<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** One row of the exógena concept table (observations PRD §4.7 d). */
final class ExogenaConceptInput
{
    #[Assert\Choice(choices: ['1001', '1007'], message: 'Choose format 1001 or 1007.')]
    public string $format = '';

    #[Assert\Regex(pattern: '/^\d{1,16}$/', message: 'Write the start of an account code (digits only).')]
    public string $accountPrefix = '';

    #[Assert\Regex(pattern: '/^\d{4}$/', message: 'Write the DIAN concept code (four digits).')]
    public string $conceptCode = '';
}
