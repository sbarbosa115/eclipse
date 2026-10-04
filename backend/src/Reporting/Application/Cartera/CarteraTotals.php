<?php

namespace App\Reporting\Application\Cartera;

/** The grand total of a cartera, by bucket. */
final readonly class CarteraTotals
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
}
