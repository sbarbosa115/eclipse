<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Cartera\CarteraRow;

/** One tercero's open balance split by ageing: al día, 1-30, 31-60, 61-90 and más de 90 días vencida. */
final readonly class CarteraRowOutput
{
    public function __construct(
        public string $terceroId,
        public string $name,
        /** "NIT 900123456-8" */
        public string $identification,
        public string $current,
        public string $days1To30,
        public string $days31To60,
        public string $days61To90,
        public string $over90,
        public string $total,
        /** Everything past its due date. */
        public string $overdue,
        /** Open receivables / payables behind the row. */
        public int $documents,
    ) {
    }

    public static function of(CarteraRow $r): self
    {
        return new self($r->terceroId, $r->name, $r->identification, $r->current, $r->days1To30, $r->days31To60, $r->days61To90, $r->over90, $r->total, $r->overdue, $r->documents);
    }
}
