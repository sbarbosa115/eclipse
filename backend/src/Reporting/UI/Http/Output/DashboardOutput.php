<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Dashboard\DashboardFigures;

/** The home screen's numbers. */
final readonly class DashboardOutput
{
    public function __construct(
        /** YYYY-MM-DD, Colombia's today. */
        public string $asOf,
        public string $clientsTotal,
        public string $clientsOverdue,
        public string $suppliersTotal,
        public string $suppliersOverdue,
        /** Invoiced so far this month, before taxes. */
        public string $salesMonth,
        public int $salesMonthCount,
        public string $purchasesMonth,
        public int $purchasesMonthCount,
        /** Cajas y bancos (1105 + 1110); null for a person who may not see the books. */
        public ?string $cashAndBanks,
    ) {
    }

    public static function of(DashboardFigures $f): self
    {
        return new self($f->asOf, $f->clientsTotal, $f->clientsOverdue, $f->suppliersTotal, $f->suppliersOverdue, $f->salesMonth, $f->salesMonthCount, $f->purchasesMonth, $f->purchasesMonthCount, $f->cashAndBanks);
    }
}
