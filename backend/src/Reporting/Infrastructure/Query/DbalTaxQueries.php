<?php

namespace App\Reporting\Infrastructure\Query;

use App\Reporting\Application\Tax\MonthRange;
use App\Reporting\Application\Tax\TaxQueries;
use App\Reporting\Application\Tax\VatRate;
use App\Reporting\Application\Tax\WithholdingKind;
use App\Reporting\Application\Tax\WithholdingLine;
use App\Reporting\Application\Tax\WithholdingSide;
use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The tax figures read straight from the documents of Sales and Purchasing (the read side owns no aggregates; both
 * sides have the same line and line-withholding shape): one grouped query per figure, whatever the number of invoices.
 * Every join repeats the company, so another company's rows can never be summed in.
 */
final class DbalTaxQueries implements TaxQueries
{
    private const TABLES = [
        'sales' => ['invoice' => 'sales_invoice', 'line' => 'sales_invoice_line', 'withholding' => 'sales_invoice_line_withholding'],
        'purchases' => ['invoice' => 'purchase_invoice', 'line' => 'purchase_invoice_line', 'withholding' => 'purchase_invoice_line_withholding'],
    ];

    /** The statuses of an invoice that counts: emitted, whatever has been paid of it. */
    private const COUNTED = "('emitted', 'partially_paid', 'paid')";

    public function __construct(private readonly Connection $db)
    {
    }

    public function withholdings(Uuid $companyId, WithholdingSide $side, MonthRange $range, array $kinds): array
    {
        $t = self::tables($side);
        $rows = $this->db->fetchAllAssociative(
            "SELECT DATE_FORMAT(i.issue_date, '%Y-%m') AS month, w.kind, t.id AS tercero_id, t.identification_type,
                    t.identification_number, t.check_digit, t.display_name, w.tax_id, w.name, w.calculation, w.value,
                    SUM(w.base_amount) AS base, SUM(w.amount) AS amount, COUNT(DISTINCT i.id) AS documents
             FROM {$t['withholding']} w
             JOIN {$t['line']} l ON l.id = w.line_id AND l.company_id = w.company_id
             JOIN {$t['invoice']} i ON i.id = l.document_id AND i.company_id = w.company_id
             JOIN tercero t ON t.id = i.tercero_id AND t.company_id = w.company_id
             WHERE w.company_id = :company AND i.status IN ".self::COUNTED."
               AND i.issue_date BETWEEN :from AND :to AND w.kind IN (:kinds)
             GROUP BY month, w.kind, t.id, t.identification_type, t.identification_number, t.check_digit, t.display_name,
                      w.tax_id, w.name, w.calculation, w.value
             ORDER BY month ASC, FIELD(w.kind, 'retefuente', 'reteiva', 'reteica'), t.display_name ASC, t.id ASC, w.name ASC, w.value ASC",
            self::bind($companyId, $range) + ['kinds' => self::kinds($kinds)],
            ['kinds' => ArrayParameterType::STRING],
        );

        return array_map(static fn (array $r): WithholdingLine => new WithholdingLine(
            (string) $r['month'],
            WithholdingKind::from((string) $r['kind']),
            Uuid::fromBinary($r['tercero_id'])->toRfc4122(),
            (string) $r['identification_type'],
            (string) $r['identification_number'],
            \is_string($r['check_digit']) && '' !== $r['check_digit'] ? $r['check_digit'] : null,
            (string) $r['display_name'],
            null === $r['tax_id'] ? null : Uuid::fromBinary($r['tax_id'])->toRfc4122(),
            (string) $r['name'],
            (string) $r['calculation'],
            (string) $r['value'],
            self::money($r['base']),
            self::money($r['amount']),
            (int) $r['documents'],
        ), $rows);
    }

    public function voidedWithWithholdings(Uuid $companyId, WithholdingSide $side, MonthRange $range, array $kinds): int
    {
        $t = self::tables($side);

        return (int) $this->db->fetchOne(
            "SELECT COUNT(DISTINCT i.id)
             FROM {$t['invoice']} i
             JOIN {$t['line']} l ON l.document_id = i.id AND l.company_id = i.company_id
             JOIN {$t['withholding']} w ON w.line_id = l.id AND w.company_id = i.company_id
             WHERE i.company_id = :company AND i.status = 'voided' AND i.issue_date BETWEEN :from AND :to AND w.kind IN (:kinds)",
            self::bind($companyId, $range) + ['kinds' => self::kinds($kinds)],
            ['kinds' => ArrayParameterType::STRING],
        );
    }

    public function vatByRate(Uuid $companyId, bool $sales, MonthRange $range): array
    {
        $t = self::TABLES[$sales ? 'sales' : 'purchases'];
        $rows = $this->db->fetchAllAssociative(
            "SELECT l.charge_value AS rate, SUM(l.subtotal_amount) AS base, SUM(l.tax_amount) AS tax
             FROM {$t['line']} l
             JOIN {$t['invoice']} i ON i.id = l.document_id AND i.company_id = l.company_id
             WHERE l.company_id = :company AND l.charge_kind = 'iva' AND i.status IN ".self::COUNTED.'
               AND i.issue_date BETWEEN :from AND :to
             GROUP BY l.charge_value
             ORDER BY l.charge_value DESC',
            self::bind($companyId, $range),
        );

        return array_map(static fn (array $r) => new VatRate((string) $r['rate'], self::money($r['base']), self::money($r['tax'])), $rows);
    }

    public function consumptionTax(Uuid $companyId, MonthRange $range): string
    {
        return self::money($this->db->fetchOne(
            "SELECT SUM(l.tax_amount)
             FROM sales_invoice_line l
             JOIN sales_invoice i ON i.id = l.document_id AND i.company_id = l.company_id
             WHERE l.company_id = :company AND l.charge_kind = 'impoconsumo' AND i.status IN ".self::COUNTED.'
               AND i.issue_date BETWEEN :from AND :to',
            self::bind($companyId, $range),
        ));
    }

    public function withholdingTotal(Uuid $companyId, WithholdingSide $side, WithholdingKind $kind, MonthRange $range): string
    {
        $t = self::tables($side);

        return self::money($this->db->fetchOne(
            "SELECT SUM(w.amount)
             FROM {$t['withholding']} w
             JOIN {$t['line']} l ON l.id = w.line_id AND l.company_id = w.company_id
             JOIN {$t['invoice']} i ON i.id = l.document_id AND i.company_id = w.company_id
             WHERE w.company_id = :company AND w.kind = :kind AND i.status IN ".self::COUNTED.'
               AND i.issue_date BETWEEN :from AND :to',
            self::bind($companyId, $range) + ['kind' => $kind->value],
        ));
    }

    public function voidedInvoices(Uuid $companyId, MonthRange $range): int
    {
        return (int) $this->db->fetchOne(
            "SELECT (SELECT COUNT(*) FROM sales_invoice WHERE company_id = :company AND status = 'voided' AND issue_date BETWEEN :from AND :to)
                  + (SELECT COUNT(*) FROM purchase_invoice WHERE company_id = :company AND status = 'voided' AND issue_date BETWEEN :from AND :to)",
            self::bind($companyId, $range),
        );
    }

    /** @return array{invoice: string, line: string, withholding: string} */
    private static function tables(WithholdingSide $side): array
    {
        return self::TABLES[WithholdingSide::Suffered === $side ? 'sales' : 'purchases'];
    }

    /** @return array<string, string> */
    private static function bind(Uuid $companyId, MonthRange $range): array
    {
        return ['company' => $companyId->toBinary(), 'from' => $range->first->format('Y-m-d'), 'to' => $range->last->format('Y-m-d')];
    }

    /**
     * @param list<WithholdingKind> $kinds
     *
     * @return list<string>
     */
    private static function kinds(array $kinds): array
    {
        return array_map(static fn (WithholdingKind $k) => $k->value, $kinds);
    }

    private static function money(mixed $value): string
    {
        return Money::of((string) ($value ?? '0'))->toString();
    }
}
