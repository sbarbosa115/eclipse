<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Exogena\ExogenaReport;
use App\Shared\Domain\Money\Money;

/** An exógena format's working paper for a taxable year (observations PRD §4.7 d). */
final readonly class ExogenaOutput
{
    /**
     * @param list<string>           $valueColumns     the format's value column keys, in order
     * @param list<ExogenaRowOutput> $rows
     * @param list<ExogenaRowOutput> $minorAmountsRows rows under the threshold, grouped under the pseudo-NIT 222222222,
     *                                                 one per concept
     * @param array<string, string>  $totals           every value column added up (rows and cuantías menores)
     */
    public function __construct(
        public int $year,
        /** 1001, 1003, 1005, 1006, 1007, 1008, 1009 */
        public string $format,
        /** The cuantías menores threshold in pesos (0.00: everyone by name). */
        public string $threshold,
        public array $valueColumns,
        public array $rows,
        public array $minorAmountsRows,
        public array $totals,
        /** Rows whose account has no concept code yet (1001 and 1007; always 0 elsewhere). */
        public int $missingConcepts,
        /** Colombian terceros reported by name with no municipality: the export is refused while any. */
        public int $missingMunicipality,
    ) {
    }

    public static function of(ExogenaReport $report): self
    {
        return new self(
            $report->year,
            $report->format->value,
            $report->threshold->toString(),
            $report->format->valueColumns(),
            array_map(ExogenaRowOutput::of(...), $report->rows),
            array_map(ExogenaRowOutput::of(...), $report->minorAmountsRows),
            array_map(static fn (Money $m) => $m->toString(), $report->totals()),
            $report->missingConcepts,
            $report->missingMunicipality,
        );
    }
}
