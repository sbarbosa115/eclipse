<?php

namespace App\Reporting\UI\Http\Output;

/** An exógena format's working paper for a taxable year (observations PRD §4.7 d). */
final readonly class ExogenaOutput
{
    /**
     * @param list<string>           $valueColumns the format's value column keys, in order
     * @param list<ExogenaRowOutput> $rows
     */
    public function __construct(
        public int $year,
        /** 1001, 1003, 1005, 1006, 1007, 1008, 1009 */
        public string $format,
        public string $threshold,
        public array $valueColumns,
        public array $rows,
        /** Rows under the threshold, grouped under the pseudo-NIT 222222222. */
        public ?ExogenaRowOutput $minorAmountsRow,
        /** Rows whose account has no concept code yet. */
        public int $missingConcepts,
        /** Terceros in the format with no municipality: the export is refused while any. */
        public int $missingMunicipality,
    ) {
    }
}
