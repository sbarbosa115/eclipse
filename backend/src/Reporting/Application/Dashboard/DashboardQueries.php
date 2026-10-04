<?php

namespace App\Reporting\Application\Dashboard;

use Symfony\Component\Uid\Uuid;

interface DashboardQueries
{
    /** Month-to-date invoicing (emitted, paid and partially paid; voided and drafts count nothing). */
    public function monthSales(Uuid $companyId, \DateTimeImmutable $today): MonthTotal;

    public function monthPurchases(Uuid $companyId, \DateTimeImmutable $today): MonthTotal;

    /** Débito − crédito of every account under 1105 and 1110 up to the day, as a decimal string. */
    public function cashAndBanks(Uuid $companyId, \DateTimeImmutable $today): string;
}
