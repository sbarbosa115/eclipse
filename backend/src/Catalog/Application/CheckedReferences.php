<?php

namespace App\Catalog\Application;

use Symfony\Component\Uid\Uuid;

/** The taxes a product ends up with, after the company's defaults filled the ones it did not name. */
final readonly class CheckedReferences
{
    public function __construct(
        public ?Uuid $chargeTaxId,
        public ?Uuid $withholdingTaxId,
    ) {
    }
}
