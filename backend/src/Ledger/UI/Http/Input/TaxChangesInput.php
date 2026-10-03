<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Edit a tax: its class and kind never change. */
final class TaxChangesInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    public string $name = '';

    #[Assert\Choice(choices: ['percentage', 'per_unit'])]
    public string $calculation = 'percentage';

    #[Assert\NotBlank]
    public string $rate = '';

    #[Assert\Uuid]
    public ?string $salesAccountId = null;

    #[Assert\Uuid]
    public ?string $purchaseAccountId = null;

    #[Assert\Date]
    public ?string $validFrom = null;

    #[Assert\Date]
    public ?string $validTo = null;
}
