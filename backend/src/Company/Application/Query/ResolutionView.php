<?php

namespace App\Company\Application\Query;

/** The invoicing resolution as the settings tab shows it. Dates are "Y-m-d". */
final readonly class ResolutionView
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
        public bool $hasIssuedNumbers,
    ) {
    }
}
