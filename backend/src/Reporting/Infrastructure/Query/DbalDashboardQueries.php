<?php

namespace App\Reporting\Infrastructure\Query;

use App\Reporting\Application\Dashboard\DashboardQueries;
use App\Reporting\Application\Dashboard\MonthTotal;
use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final class DbalDashboardQueries implements DashboardQueries
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function monthSales(Uuid $companyId, \DateTimeImmutable $today): MonthTotal
    {
        return $this->month('sales_invoice', $companyId, $today);
    }

    public function monthPurchases(Uuid $companyId, \DateTimeImmutable $today): MonthTotal
    {
        return $this->month('purchase_invoice', $companyId, $today);
    }

    public function cashAndBanks(Uuid $companyId, \DateTimeImmutable $today): string
    {
        $sum = $this->db->fetchOne(
            "SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM journal_line l
             JOIN journal_entry e ON e.id = l.entry_id AND e.company_id = l.company_id
             WHERE l.company_id = :company AND e.entry_date <= :today
               AND (l.account_code LIKE '1105%' OR l.account_code LIKE '1110%')",
            ['company' => $companyId->toBinary(), 'today' => $today->format('Y-m-d')],
        );

        return Money::of((string) $sum)->toString();
    }

    private function month(string $table, Uuid $companyId, \DateTimeImmutable $today): MonthTotal
    {
        $row = $this->db->fetchAssociative(
            "SELECT COALESCE(SUM(subtotal), 0) AS amount, COUNT(*) AS documents FROM $table
             WHERE company_id = :company AND status IN ('emitted', 'partially_paid', 'paid')
               AND issue_date BETWEEN :first AND :today",
            ['company' => $companyId->toBinary(), 'first' => $today->format('Y-m-01'), 'today' => $today->format('Y-m-d')],
        ) ?: ['amount' => 0, 'documents' => 0];

        return new MonthTotal(Money::of((string) $row['amount'])->toString(), (int) $row['documents']);
    }
}
