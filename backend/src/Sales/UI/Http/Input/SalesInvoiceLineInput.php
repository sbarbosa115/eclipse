<?php

namespace App\Sales\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class SalesInvoiceLineInput
{
    #[Assert\NotBlank(message: 'Choose a product or service.')]
    #[Assert\Uuid]
    public ?string $productId = null;

    #[Assert\NotBlank(message: 'Write the description.')]
    #[Assert\Length(max: 500)]
    public string $description = '';

    /** Up to four decimals, greater than zero. */
    #[Assert\Regex(pattern: '/^(?=.*[1-9])\d{1,10}(\.\d{1,4})?$/', message: 'The quantity is greater than zero, with at most four decimals.')]
    public string $quantity = '';

    /** Pesos, up to four decimals. */
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,4})?$/', message: 'Write the price in pesos, with at most four decimals.')]
    public string $unitPrice = '';

    /** % Descuento: 0 to 100, up to four decimals; empty is none. */
    #[Assert\Regex(pattern: '/^((100(\.0{1,4})?)|(\d{1,2}(\.\d{1,4})?))?$/', message: 'The discount is a percentage from 0 to 100.')]
    public string $discount = '';

    /** Null: no impuesto cargo. */
    #[Assert\Uuid]
    public ?string $chargeTaxId = null;

    /** Null: no retención. */
    #[Assert\Uuid]
    public ?string $withholdingTaxId = null;
}
