<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Cartera\CarteraTotals;

/** The grand total of a cartera, by bucket (for every tercero matching the search, not just the page). */
final readonly class CarteraTotalsOutput
{
    public function __construct(
        public string $current,
        public string $days1To30,
        public string $days31To60,
        public string $days61To90,
        public string $over90,
        public string $total,
        public string $overdue,
        public int $terceros,
        public int $documents,
    ) {
    }

    public static function of(CarteraTotals $t): self
    {
        return new self($t->current, $t->days1To30, $t->days31To60, $t->days61To90, $t->over90, $t->total, $t->overdue, $t->terceros, $t->documents);
    }
}
