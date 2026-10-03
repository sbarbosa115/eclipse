<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Create a tax. The rate is a decimal string; dates are "YYYY-MM-DD". */
final class TaxInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    public string $name = '';

    #[Assert\Choice(choices: ['charge', 'withholding'])]
    public string $taxClass = 'charge';

    #[Assert\Choice(choices: ['none', 'iva', 'impoconsumo', 'retefuente', 'reteiva', 'reteica'])]
    public string $kind = 'iva';

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
