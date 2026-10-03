<?php

namespace App\Catalog\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Quick-create from a document line (§4.3): what a line needs, nothing else. */
final class QuickProductInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['producto', 'servicio'])]
    public string $type = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,4})?$/', message: 'Write the price in pesos, with at most four decimals.')]
    public string $salePrice = '';

    public bool $priceIncludesTax = false;

    /** The company's default charge tax when missing. */
    #[Assert\Uuid]
    public ?string $chargeTaxId = null;

    /** The company's default withholding tax when missing. */
    #[Assert\Uuid]
    public ?string $withholdingTaxId = null;
}
