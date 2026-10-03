<?php

namespace App\Ledger\Application\Query;

/**
 * A tax as documents and the UI read it. rate is a decimal string: a percentage or a value per unit (calculation).
 */
final readonly class TaxView
{
    public function __construct(
        public string $id,
        public string $name,
        /** charge or withholding */
        public string $taxClass,
        /** none, iva, impoconsumo, retefuente, reteiva, reteica */
        public string $kind,
        /** percentage or per_unit */
        public string $calculation,
        public string $rate,
        public ?string $salesAccountId,
        public ?string $purchaseAccountId,
        public ?string $validFrom,
        public ?string $validTo,
        public bool $active,
        public bool $standard,
    ) {
    }
}
