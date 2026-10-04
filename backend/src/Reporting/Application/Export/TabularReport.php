<?php

namespace App\Reporting\Application\Export;

/**
 * Any report as a table: what the CSV writer and the PDF template both read, so every report exports the same way.
 * Rows may be a generator (a CSV streams them); `totals` is asked for after the rows were read, so a streamed report
 * can add them up as it goes.
 */
final class TabularReport
{
    /**
     * @param list<ReportColumn>                    $columns
     * @param iterable<list<string>>                $rows     one value per column, raw (decimal strings, Y-m-d dates)
     * @param \Closure(): (list<string>|null)|null  $totals   the totals row, read after the rows
     * @param list<string>                          $subtitle lines under the title (the period, the filter)
     */
    public function __construct(
        public readonly string $title,
        public readonly string $fileName,
        public readonly array $columns,
        public readonly iterable $rows,
        public readonly ?\Closure $totals = null,
        public readonly array $subtitle = [],
    ) {
    }

    /** @return list<string>|null */
    public function totalsRow(): ?array
    {
        return null === $this->totals ? null : ($this->totals)();
    }
}
