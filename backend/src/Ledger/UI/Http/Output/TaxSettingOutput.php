<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Query\TaxSettingView;

/** A tax on the Impuestos tab: what GET /taxes answers, plus the accounts' names and whether a document uses it. */
final readonly class TaxSettingOutput
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
        public ?string $salesAccountCode,
        public ?string $salesAccountName,
        public ?string $purchaseAccountId,
        public ?string $purchaseAccountCode,
        public ?string $purchaseAccountName,
        public ?string $validFrom,
        public ?string $validTo,
        public bool $active,
        public bool $standard,
        /** A document, a product or the company uses it: it can only be deactivated. */
        public bool $inUse,
    ) {
    }

    public static function of(TaxSettingView $v): self
    {
        $t = $v->tax;

        return new self($t->id, $t->name, $t->taxClass, $t->kind, $t->calculation, $t->rate, $t->salesAccountId, $v->salesAccount?->code, $v->salesAccount?->name, $t->purchaseAccountId, $v->purchaseAccount?->code, $v->purchaseAccount?->name, $t->validFrom, $t->validTo, $t->active, $t->standard, $v->inUse);
    }
}
