<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Query\TaxView;

final readonly class TaxOutput
{
    public function __construct(
        public string $id,
        public string $name,
        /** charge (impuesto cargo) or withholding (retención) */
        public string $taxClass,
        /** none, iva, impoconsumo, retefuente, reteiva, reteica */
        public string $kind,
        /** percentage or per_unit */
        public string $calculation,
        /** Decimal string: a percentage ("19.0000") or a value per unit ("500.0000"). */
        public string $rate,
        public ?string $salesAccountId,
        public ?string $purchaseAccountId,
        public ?string $validFrom,
        public ?string $validTo,
        public bool $active,
        public bool $standard,
    ) {
    }

    public static function of(TaxView $v): self
    {
        return new self($v->id, $v->name, $v->taxClass, $v->kind, $v->calculation, $v->rate, $v->salesAccountId, $v->purchaseAccountId, $v->validFrom, $v->validTo, $v->active, $v->standard);
    }
}
