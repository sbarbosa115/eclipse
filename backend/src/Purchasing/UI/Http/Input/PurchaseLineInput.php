<?php

namespace App\Purchasing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** A line: a product or an expense account (§4.10), quantity, unit value, % discount and the two taxes (null: none). */
final class PurchaseLineInput
{
    #[Assert\Uuid]
    public ?string $productId = null;

    #[Assert\Uuid]
    public ?string $accountId = null;

    #[Assert\Length(max: 500)]
    public string $description = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,4})?$/', message: 'Write a quantity greater than zero, with at most four decimals.')]
    #[Assert\Regex(pattern: '/^0+(\.0+)?$/', match: false, message: 'Write a quantity greater than zero, with at most four decimals.')]
    public string $quantity = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,4})?$/', message: 'Write the value in pesos, with at most four decimals.')]
    public string $unitPrice = '';

    #[Assert\Regex(pattern: '/^(100(\.0{1,4})?|\d{1,2}(\.\d{1,4})?)$/', message: 'The discount is between 0 and 100, with at most four decimals.')]
    public ?string $discount = null;

    #[Assert\Uuid]
    public ?string $chargeTaxId = null;

    #[Assert\Uuid]
    public ?string $withholdingTaxId = null;
}
