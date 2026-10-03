<?php

namespace App\Catalog\UI\Http\Input;

use App\Catalog\Domain\Model\UnitOfMeasure;
use Symfony\Component\Validator\Constraints as Assert;

/** The full product form (create and update). */
final class ProductInput
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

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\Uuid]
    public ?string $categoryId = null;

    /** A DIAN unit code (UnitOfMeasure); the type's default when missing. */
    #[Assert\Choice(callback: [UnitOfMeasure::class, 'codes'])]
    public ?string $unitCode = null;

    /** Pesos, up to four decimals ("119000", "49999.9900"). */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,4})?$/', message: 'Write the price in pesos, with at most four decimals.')]
    public string $salePrice = '';

    public bool $priceIncludesTax = false;

    #[Assert\Uuid]
    public ?string $chargeTaxId = null;

    #[Assert\Uuid]
    public ?string $withholdingTaxId = null;

    #[Assert\Uuid]
    public ?string $revenueAccountId = null;

    #[Assert\Uuid]
    public ?string $expenseAccountId = null;
}
