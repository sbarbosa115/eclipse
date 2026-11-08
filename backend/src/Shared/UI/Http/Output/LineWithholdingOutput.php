<?php

namespace App\Shared\UI\Http\Output;

use App\Shared\Application\Document\LineWithholdingView;

/** One retención of a line: the copied tax, its base and its share of the document's Retenciones. */
final readonly class LineWithholdingOutput
{
    public function __construct(
        public ?string $taxId,
        public string $name,
        /** retefuente, reteiva, reteica */
        public string $kind,
        /** percentage or per_unit */
        public string $calculation,
        public string $rate,
        public ?string $accountId,
        public string $baseAmount,
        public string $amount,
    ) {
    }

    public static function of(LineWithholdingView $v): self
    {
        return new self($v->taxId, $v->name, $v->kind, $v->calculation, $v->rate, $v->accountId, $v->baseAmount, $v->amount);
    }
}
