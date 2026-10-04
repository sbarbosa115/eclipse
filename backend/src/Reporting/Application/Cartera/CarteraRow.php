<?php

namespace App\Reporting\Application\Cartera;

/** One tercero's open balance, split by ageing bucket. Money as decimal strings. */
final readonly class CarteraRow
{
    public function __construct(
        public string $terceroId,
        public string $name,
        public string $identification,
        public string $current,
        public string $days1To30,
        public string $days31To60,
        public string $days61To90,
        public string $over90,
        public string $total,
        /** Everything past its due date: the total less al día. */
        public string $overdue,
        public int $documents,
    ) {
    }
}
