<?php

namespace App\Reporting\Infrastructure\Query;

use App\Reporting\Application\Exogena\ExogenaAmounts;
use App\Reporting\Application\Exogena\ExogenaFormat;
use App\Reporting\Application\Exogena\ExogenaQueries;
use App\Reporting\Application\Exogena\ExogenaTercero;
use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The exógena figures read straight from the documents of Sales and Purchasing, one grouped query per format whatever
 * the number of invoices. Every join repeats the company, so another company's rows can never be summed in.
 *
 * The account of a line is the one its posting used (PurchaseInvoiceEntry, SalesInvoicePosting): on purchases the
 * line's own account, else the product's expense account, else the rule of compra_mercancias (a producto) or
 * gasto_por_defecto (a servicio); on sales the product's revenue account, else the rule of ingreso. A product's account
 * changed after the invoice is read as it is now (README, known gaps).
 */
final class DbalExogenaQueries implements ExogenaQueries
{
    /** The statuses of an invoice that counts: emitted, whatever has been paid of it. */
    private const COUNTED = "('emitted', 'partially_paid', 'paid')";

    public function __construct(private readonly Connection $db)
    {
    }

    public function amounts(Uuid $companyId, ExogenaFormat $format, int $year): array
    {
        [$sql, $columns, $byAccount] = match ($format) {
            ExogenaFormat::Payments => [self::payments(), ['payment', 'vat_cost', 'withholding', 'vat_withholding'], true],
            ExogenaFormat::WithholdingsSuffered => [self::withholdingsSuffered(), ['base', 'withholding'], false],
            ExogenaFormat::VatDeductible => [self::vat('purchase_invoice', "SUM(l.tax_amount) AS deductible_vat, 0 AS returned_vat", "l.charge_kind = 'iva'"), ['deductible_vat', 'returned_vat'], false],
            ExogenaFormat::VatGenerated => [self::vat(
                'sales_invoice',
                "SUM(CASE WHEN l.charge_kind = 'iva' THEN l.tax_amount ELSE 0 END) AS generated_vat,
                 SUM(CASE WHEN l.charge_kind = 'impoconsumo' THEN l.tax_amount ELSE 0 END) AS consumption_tax",
                "l.charge_kind IN ('iva', 'impoconsumo')",
            ), ['generated_vat', 'consumption_tax'], false],
            ExogenaFormat::Income => [self::income(), ['gross_income', 'returns'], true],
            ExogenaFormat::Receivables, ExogenaFormat::Payables => throw new \LogicException('1008 and 1009 are the cartera at 31/12 (CarteraQueries).'),
        };

        $rows = $this->db->fetchAllAssociative($sql, [
            'company' => $companyId->toBinary(),
            'from' => \sprintf('%d-01-01', $year),
            'to' => \sprintf('%d-12-31', $year),
        ]);

        return array_map(static function (array $r) use ($columns, $byAccount): ExogenaAmounts {
            $values = [];
            foreach ($columns as $column) {
                $values[$column] = Money::of((string) ($r[$column] ?? '0'))->toString();
            }

            return new ExogenaAmounts(Uuid::fromBinary($r['tercero_id'])->toRfc4122(), $byAccount ? (string) $r['account_code'] : null, $values);
        }, $rows);
    }

    public function terceros(Uuid $companyId, array $terceroIds): array
    {
        if ([] === $terceroIds) {
            return [];
        }
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, identification_type, identification_number, check_digit, person_type, first_names, last_names,
                    business_name, display_name, address, municipality_code, country_code
             FROM tercero WHERE company_id = :company AND id IN (:ids)',
            ['company' => $companyId->toBinary(), 'ids' => array_map(static fn (string $id) => Uuid::fromString($id)->toBinary(), $terceroIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $terceros = [];
        foreach ($rows as $r) {
            $id = Uuid::fromBinary($r['id'])->toRfc4122();
            $person = 'persona' === $r['person_type'];
            $terceros[$id] = new ExogenaTercero(
                $id,
                (string) $r['identification_type'],
                (string) $r['identification_number'],
                self::text($r['check_digit']),
                (string) $r['person_type'],
                $person ? self::text($r['first_names']) : null,
                $person ? self::text($r['last_names']) : null,
                // A company's razón social; a person is named by apellidos and nombres instead.
                $person ? null : (self::text($r['business_name']) ?? (string) $r['display_name']),
                self::text($r['address']),
                self::text($r['municipality_code']),
                (string) ($r['country_code'] ?? 'CO'),
            );
        }

        return $terceros;
    }

    /** 1001: per supplier and expense account, the payment (impoconsumo is part of the cost) and its retenciones. */
    private static function payments(): string
    {
        return "SELECT i.tercero_id, a.code AS account_code,
                   SUM(l.subtotal_amount + CASE WHEN l.charge_kind = 'impoconsumo' THEN l.tax_amount ELSE 0 END) AS payment,
                   0 AS vat_cost,
                   SUM(COALESCE(w.retefuente, 0)) AS withholding,
                   SUM(COALESCE(w.reteiva, 0)) AS vat_withholding
                FROM purchase_invoice_line l
                JOIN purchase_invoice i ON i.id = l.document_id AND i.company_id = l.company_id
                JOIN tercero t ON t.id = i.tercero_id AND t.company_id = l.company_id
                LEFT JOIN product p ON p.id = l.product_id AND p.company_id = l.company_id
                LEFT JOIN posting_rule r ON r.company_id = l.company_id
                     AND r.concept = CASE WHEN p.type = 'producto' THEN 'compra_mercancias' ELSE 'gasto_por_defecto' END
                JOIN ledger_account a ON a.id = COALESCE(l.account_id, p.expense_account_id, r.account_id) AND a.company_id = l.company_id
                LEFT JOIN (
                    SELECT line_id,
                           SUM(CASE WHEN kind = 'retefuente' THEN amount ELSE 0 END) AS retefuente,
                           SUM(CASE WHEN kind = 'reteiva' THEN amount ELSE 0 END) AS reteiva
                    FROM purchase_invoice_line_withholding WHERE company_id = :company GROUP BY line_id
                ) w ON w.line_id = l.id
                WHERE l.company_id = :company AND i.status IN ".self::COUNTED.' AND i.issue_date BETWEEN :from AND :to
                GROUP BY i.tercero_id, t.display_name, a.code
                ORDER BY t.display_name ASC, i.tercero_id ASC, a.code ASC';
    }

    /** 1003: per client, the base and the retefuente it practised (sufrida by the company). */
    private static function withholdingsSuffered(): string
    {
        return "SELECT i.tercero_id, SUM(w.base_amount) AS base, SUM(w.amount) AS withholding
                FROM sales_invoice_line_withholding w
                JOIN sales_invoice_line l ON l.id = w.line_id AND l.company_id = w.company_id
                JOIN sales_invoice i ON i.id = l.document_id AND i.company_id = w.company_id
                JOIN tercero t ON t.id = i.tercero_id AND t.company_id = w.company_id
                WHERE w.company_id = :company AND w.kind = 'retefuente' AND i.status IN ".self::COUNTED.'
                  AND i.issue_date BETWEEN :from AND :to
                GROUP BY i.tercero_id, t.display_name
                ORDER BY t.display_name ASC, i.tercero_id ASC';
    }

    /** 1005 and 1006: per tercero, the charge taxes of the lines. */
    private static function vat(string $invoice, string $sums, string $filter): string
    {
        return "SELECT i.tercero_id, $sums
                FROM {$invoice}_line l
                JOIN $invoice i ON i.id = l.document_id AND i.company_id = l.company_id
                JOIN tercero t ON t.id = i.tercero_id AND t.company_id = l.company_id
                WHERE l.company_id = :company AND $filter AND l.tax_amount <> 0 AND i.status IN ".self::COUNTED.'
                  AND i.issue_date BETWEEN :from AND :to
                GROUP BY i.tercero_id, t.display_name
                ORDER BY t.display_name ASC, i.tercero_id ASC';
    }

    /** 1007: per client and revenue account, the gross income and its discounts. */
    private static function income(): string
    {
        return "SELECT i.tercero_id, a.code AS account_code, SUM(l.gross_amount) AS gross_income, SUM(l.discount_amount) AS returns
                FROM sales_invoice_line l
                JOIN sales_invoice i ON i.id = l.document_id AND i.company_id = l.company_id
                JOIN tercero t ON t.id = i.tercero_id AND t.company_id = l.company_id
                LEFT JOIN product p ON p.id = l.product_id AND p.company_id = l.company_id
                LEFT JOIN posting_rule r ON r.company_id = l.company_id AND r.concept = 'ingreso'
                JOIN ledger_account a ON a.id = COALESCE(p.revenue_account_id, r.account_id) AND a.company_id = l.company_id
                WHERE l.company_id = :company AND i.status IN ".self::COUNTED.' AND i.issue_date BETWEEN :from AND :to
                GROUP BY i.tercero_id, t.display_name, a.code
                ORDER BY t.display_name ASC, i.tercero_id ASC, a.code ASC';
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? $value : null;
    }
}
