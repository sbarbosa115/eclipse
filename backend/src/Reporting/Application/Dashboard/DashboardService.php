<?php

namespace App\Reporting\Application\Dashboard;

use App\Reporting\Application\Cartera\CarteraQueries;
use App\Reporting\Application\Cartera\CarteraSide;
use App\Reporting\Application\ReportingCalendar;
use Symfony\Component\Uid\Uuid;

/** What the home screen shows: five queries whatever the size of the company, nothing per tercero or per document. */
final class DashboardService
{
    public function __construct(
        private readonly CarteraQueries $cartera,
        private readonly DashboardQueries $queries,
        private readonly ReportingCalendar $calendar,
    ) {
    }

    public function figures(Uuid $companyId, bool $mayViewBooks): DashboardFigures
    {
        $today = $this->calendar->today();
        $clients = $this->cartera->totals($companyId, CarteraSide::Clients, $today);
        $suppliers = $this->cartera->totals($companyId, CarteraSide::Suppliers, $today);
        $sales = $this->queries->monthSales($companyId, $today);
        $purchases = $this->queries->monthPurchases($companyId, $today);

        return new DashboardFigures(
            $today->format('Y-m-d'),
            $clients->total,
            $clients->overdue,
            $suppliers->total,
            $suppliers->overdue,
            $sales->amount,
            $sales->count,
            $purchases->amount,
            $purchases->count,
            $mayViewBooks ? $this->queries->cashAndBanks($companyId, $today) : null,
        );
    }
}
