<?php

namespace App\Shared\UI\Http\Output;

use App\Shared\Application\Document\WithholdingSummaryView;

/** A document's retenciones by tax: what the totals panel and the PDF list. */
final readonly class WithholdingSummaryOutput
{
    public function __construct(
        public ?string $taxId,
        public string $name,
        public string $kind,
        public string $rate,
        public string $base,
        public string $amount,
    ) {
    }

    public static function of(WithholdingSummaryView $v): self
    {
        return new self($v->taxId, $v->name, $v->kind, $v->rate, $v->base, $v->amount);
    }
}
