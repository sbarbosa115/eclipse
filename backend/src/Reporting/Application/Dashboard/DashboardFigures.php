<?php

namespace App\Reporting\Application\Dashboard;

/** The few numbers of the home screen. Money as decimal strings. */
final readonly class DashboardFigures
{
    public function __construct(
        public string $asOf,
        public string $clientsTotal,
        public string $clientsOverdue,
        public string $suppliersTotal,
        public string $suppliersOverdue,
        /** Invoiced this month, before taxes. */
        public string $salesMonth,
        public int $salesMonthCount,
        public string $purchasesMonth,
        public int $purchasesMonthCount,
        /** Cajas y bancos (1105 + 1110) from the books; null when the person may not see the books. */
        public ?string $cashAndBanks,
    ) {
    }
}
