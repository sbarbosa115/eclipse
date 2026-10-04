<?php

namespace App\Reporting\Infrastructure\Query;

use App\Reporting\Application\Cartera\AgeingBucket;
use App\Reporting\Application\Cartera\CarteraDocument;
use App\Reporting\Application\Cartera\CarteraQueries;
use App\Reporting\Application\Cartera\CarteraRow;
use App\Reporting\Application\Cartera\CarteraSide;
use App\Reporting\Application\Cartera\CarteraTotals;
use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Cartera read straight from the tables of Sales and Purchasing (the read side owns no aggregates, and the receivable
 * and payable tables have the same shape). One query a request whatever the number of terceros: the open items as of
 * the date are a derived table, and the ageing is a CASE over it.
 *
 * "As of" = the invoice was issued on or before the date and was not voided by then (a void is the date of its
 * reversing entry), less the allocations of receipts / payments dated on or before it that were not voided by then.
 * For today that is the receivable's own balance; the cartera total equals the 1305 / 2205 balance of the books
 * (§5 invariant 3), which CarteraInvariantTest asserts.
 */
final class DbalCarteraQueries implements CarteraQueries
{
    private const TABLES = [
        'clients' => ['item' => 'receivable', 'invoice' => 'sales_invoice', 'document' => 'cash_receipt', 'allocation' => 'cash_receipt_allocation', 'fk' => 'receipt_id'],
        'suppliers' => ['item' => 'payable', 'invoice' => 'purchase_invoice', 'document' => 'supplier_payment', 'allocation' => 'supplier_payment_allocation', 'fk' => 'payment_id'],
    ];

    private const IDENTIFICATION = ['nit' => 'NIT', 'cc' => 'C.C.', 'ce' => 'C.E.', 'pasaporte' => 'Pasaporte', 'ti' => 'T.I.'];

    public function __construct(private readonly Connection $db)
    {
    }

    public function summary(Uuid $companyId, CarteraSide $side, \DateTimeImmutable $asOf, ?string $search, ?int $limit = null, int $offset = 0): array
    {
        [$filter, $params] = $this->searchFilter($search);
        $sql = 'SELECT t.id, t.display_name, t.identification_type, t.identification_number, t.check_digit,
                  '.self::sums().', COUNT(*) AS documents
                FROM ('.$this->openItems($side).') x
                JOIN tercero t ON t.id = x.tercero_id AND t.company_id = :company
                WHERE x.balance <> 0'.$filter.'
                GROUP BY t.id, t.display_name, t.identification_type, t.identification_number, t.check_digit
                ORDER BY total DESC, t.display_name ASC, t.id ASC'
            .(null === $limit ? '' : \sprintf(' LIMIT %d OFFSET %d', $limit, max(0, $offset)));

        $rows = $this->db->fetchAllAssociative($sql, $this->bind($companyId, $asOf) + $params);

        return array_map(fn (array $r): CarteraRow => new CarteraRow(
            Uuid::fromBinary($r['id'])->toRfc4122(),
            (string) $r['display_name'],
            self::identification((string) $r['identification_type'], (string) $r['identification_number'], $r['check_digit']),
            self::money($r['b_current']),
            self::money($r['b_1_30']),
            self::money($r['b_31_60']),
            self::money($r['b_61_90']),
            self::money($r['b_over_90']),
            self::money($r['total']),
            self::money($r['overdue']),
            (int) $r['documents'],
        ), $rows);
    }

    public function totals(Uuid $companyId, CarteraSide $side, \DateTimeImmutable $asOf, ?string $search = null): CarteraTotals
    {
        [$filter, $params] = $this->searchFilter($search);
        $r = $this->db->fetchAssociative(
            'SELECT '.self::sums().', COUNT(DISTINCT x.tercero_id) AS terceros, COUNT(*) AS documents
             FROM ('.$this->openItems($side).') x
             JOIN tercero t ON t.id = x.tercero_id AND t.company_id = :company
             WHERE x.balance <> 0'.$filter,
            $this->bind($companyId, $asOf) + $params,
        ) ?: [];

        return new CarteraTotals(
            self::money($r['b_current'] ?? 0),
            self::money($r['b_1_30'] ?? 0),
            self::money($r['b_31_60'] ?? 0),
            self::money($r['b_61_90'] ?? 0),
            self::money($r['b_over_90'] ?? 0),
            self::money($r['total'] ?? 0),
            self::money($r['overdue'] ?? 0),
            (int) ($r['terceros'] ?? 0),
            (int) ($r['documents'] ?? 0),
        );
    }

    public function documents(Uuid $companyId, CarteraSide $side, \DateTimeImmutable $asOf, ?Uuid $terceroId = null, ?string $search = null): array
    {
        [$filter, $params] = $this->searchFilter($search);
        $params = $this->bind($companyId, $asOf) + $params;
        if (null !== $terceroId) {
            $filter .= ' AND x.tercero_id = :tercero';
            $params['tercero'] = $terceroId->toBinary();
        }
        $rows = $this->db->fetchAllAssociative(
            'SELECT x.id, x.invoice_id, x.invoice_number, x.tercero_id, t.display_name, x.issue_date, x.due_date, x.amount, x.balance
             FROM ('.$this->openItems($side).') x
             JOIN tercero t ON t.id = x.tercero_id AND t.company_id = :company
             WHERE x.balance <> 0'.$filter.'
             ORDER BY x.due_date ASC, x.invoice_number ASC, x.id ASC',
            $params,
        );

        return array_map(static function (array $r) use ($asOf): CarteraDocument {
            $due = new \DateTimeImmutable((string) $r['due_date']);
            $days = AgeingBucket::daysOverdue($due, $asOf);

            return new CarteraDocument(
                Uuid::fromBinary($r['id'])->toRfc4122(),
                Uuid::fromBinary($r['invoice_id'])->toRfc4122(),
                (string) $r['invoice_number'],
                Uuid::fromBinary($r['tercero_id'])->toRfc4122(),
                (string) $r['display_name'],
                (string) $r['issue_date'],
                (string) $r['due_date'],
                self::money($r['amount']),
                self::money($r['balance']),
                $days,
                AgeingBucket::forDaysOverdue($days),
            );
        }, $rows);
    }

    /** The open items as of :asof, one row per receivable / payable with the balance it had then. */
    private function openItems(CarteraSide $side): string
    {
        $t = self::TABLES[$side->value];

        return "SELECT r.id, r.invoice_id, r.invoice_number, r.tercero_id, r.issue_date, r.due_date, r.amount,
                  r.amount - COALESCE((
                    SELECT SUM(a.amount) FROM {$t['allocation']} a
                    JOIN {$t['document']} d ON d.id = a.{$t['fk']} AND d.company_id = a.company_id
                    LEFT JOIN journal_entry dv ON dv.id = d.reversal_entry_id
                    WHERE a.open_item_id = r.id AND a.company_id = r.company_id AND d.receipt_date <= :asof
                      AND (d.status <> 'voided' OR dv.entry_date > :asof)
                  ), 0) AS balance
                FROM {$t['item']} r
                JOIN {$t['invoice']} i ON i.id = r.invoice_id AND i.company_id = r.company_id
                LEFT JOIN journal_entry iv ON iv.id = i.reversal_entry_id
                WHERE r.company_id = :company AND r.issue_date <= :asof
                  AND (i.status <> 'voided' OR iv.entry_date > :asof)";
    }

    private static function sums(): string
    {
        return 'SUM(CASE WHEN DATEDIFF(:asof, x.due_date) <= 0 THEN x.balance ELSE 0 END) AS b_current,
                SUM(CASE WHEN DATEDIFF(:asof, x.due_date) BETWEEN 1 AND 30 THEN x.balance ELSE 0 END) AS b_1_30,
                SUM(CASE WHEN DATEDIFF(:asof, x.due_date) BETWEEN 31 AND 60 THEN x.balance ELSE 0 END) AS b_31_60,
                SUM(CASE WHEN DATEDIFF(:asof, x.due_date) BETWEEN 61 AND 90 THEN x.balance ELSE 0 END) AS b_61_90,
                SUM(CASE WHEN DATEDIFF(:asof, x.due_date) > 90 THEN x.balance ELSE 0 END) AS b_over_90,
                SUM(x.balance) AS total,
                SUM(CASE WHEN DATEDIFF(:asof, x.due_date) > 0 THEN x.balance ELSE 0 END) AS overdue';
    }

    /** @return array<string, string> */
    private function bind(Uuid $companyId, \DateTimeImmutable $asOf): array
    {
        return ['company' => $companyId->toBinary(), 'asof' => $asOf->format('Y-m-d')];
    }

    /** @return array{string, array<string, string>} */
    private function searchFilter(?string $search): array
    {
        $search = null === $search ? '' : trim($search);
        if ('' === $search) {
            return ['', []];
        }

        return [' AND (t.display_name LIKE :search OR t.identification_number LIKE :search)', ['search' => '%'.addcslashes($search, '%_\\').'%']];
    }

    private static function money(mixed $value): string
    {
        return Money::of((string) ($value ?? '0'))->toString();
    }

    private static function identification(string $type, string $number, mixed $checkDigit): string
    {
        $label = self::IDENTIFICATION[$type] ?? strtoupper($type);

        return $label.' '.$number.(\is_string($checkDigit) && '' !== $checkDigit ? '-'.$checkDigit : '');
    }
}
