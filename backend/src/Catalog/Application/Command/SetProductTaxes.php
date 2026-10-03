<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

/** "Aplicar estos impuestos al producto de ahora en adelante" (§4.6): a null tax is "none". */
final readonly class SetProductTaxes
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $productId,
        public ?Uuid $chargeTaxId,
        public ?Uuid $withholdingTaxId,
    ) {
    }
}
