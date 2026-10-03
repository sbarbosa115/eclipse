<?php

namespace App\Catalog\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Both taxes are written as given: a null is "no tax". */
final class ProductTaxesInput
{
    #[Assert\Uuid]
    public ?string $chargeTaxId = null;

    #[Assert\Uuid]
    public ?string $withholdingTaxId = null;
}
