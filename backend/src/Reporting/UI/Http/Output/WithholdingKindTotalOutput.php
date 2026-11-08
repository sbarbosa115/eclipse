<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Tax\WithholdingGroup;

/** One kind of retención over the whole range: the documents' totals, the books' figure and the difference. */
final readonly class WithholdingKindTotalOutput
{
    public function __construct(
        /** retefuente, reteiva, reteica */
        public string $kind,
        public string $totalBase,
        public string $totalAmount,
        public string $ledgerAmount,
        public string $difference,
    ) {
    }

    public static function of(WithholdingGroup $g): self
    {
        return new self($g->kind->value, $g->totalBase->toString(), $g->totalAmount->toString(), $g->ledgerAmount->toString(), $g->difference()->toString());
    }
}
