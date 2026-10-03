<?php

namespace App\Company\UI\Http\Output;

use App\Company\Application\Query\ResolutionView;

/** The invoicing resolution (§4.1). Dates are "Y-m-d". */
final readonly class ResolutionOutput
{
    public function __construct(
        public string $id,
        public string $resolutionNumber,
        public string $prefix,
        public int $rangeFrom,
        public int $rangeTo,
        public string $validFrom,
        public string $validTo,
        /** electronic or manual */
        public string $mode,
        /** The consecutivo actual: the number the next invoice takes. */
        public int $nextNumber,
        /** Invoices were numbered from it: desde and the prefix are locked. */
        public bool $hasIssuedNumbers,
    ) {
    }

    public static function of(ResolutionView $v): self
    {
        return new self($v->id, $v->resolutionNumber, $v->prefix, $v->rangeFrom, $v->rangeTo, $v->validFrom, $v->validTo, $v->mode, $v->nextNumber, $v->hasIssuedNumbers);
    }
}
