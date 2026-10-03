<?php

namespace App\Ledger\Infrastructure\Persistence;

use App\Ledger\Application\Port\CatalogUsage;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Looks for the tax or payment method in the tables of the contexts whose rows copy or point at it. A table or column a
 * later item adds that points at one is added here (the foreign keys stop a delete anyway, with a 500).
 */
final class DbalCatalogUsage implements CatalogUsage
{
    /** table => the columns that hold a tax id (a line's copy of it, a product's or the company's default) */
    private const TAX_COLUMNS = [
        'quotation_line' => ['charge_tax_id', 'withholding_tax_id'],
        'sales_invoice_line' => ['charge_tax_id', 'withholding_tax_id'],
        'purchase_invoice_line' => ['charge_tax_id', 'withholding_tax_id'],
        'product' => ['charge_tax_id', 'withholding_tax_id'],
    ];

    /** The company row keeps its defaults under other column names and is keyed by id. */
    private const COMPANY_TAX_COLUMNS = ['default_charge_tax_id', 'default_withholding_tax_id'];

    /** table => the column that holds a payment method id */
    private const METHOD_TABLES = ['sales_invoice_payment', 'purchase_invoice_payment', 'cash_receipt', 'supplier_payment'];

    public function __construct(private readonly Connection $db)
    {
    }

    public function taxIsUsed(Uuid $companyId, Uuid $taxId): bool
    {
        foreach (self::TAX_COLUMNS as $table => $columns) {
            $where = implode(' OR ', array_map(static fn (string $c) => "$c = :id", $columns));
            if ($this->exists("SELECT 1 FROM $table WHERE company_id = :company AND ($where) LIMIT 1", $companyId, $taxId)) {
                return true;
            }
        }
        $where = implode(' OR ', array_map(static fn (string $c) => "$c = :id", self::COMPANY_TAX_COLUMNS));

        return $this->exists("SELECT 1 FROM company WHERE id = :company AND ($where) LIMIT 1", $companyId, $taxId);
    }

    public function paymentMethodIsUsed(Uuid $companyId, Uuid $paymentMethodId): bool
    {
        foreach (self::METHOD_TABLES as $table) {
            if ($this->exists("SELECT 1 FROM $table WHERE company_id = :company AND payment_method_id = :id LIMIT 1", $companyId, $paymentMethodId)) {
                return true;
            }
        }

        return false;
    }

    private function exists(string $sql, Uuid $companyId, Uuid $id): bool
    {
        return false !== $this->db->fetchOne($sql, ['company' => $companyId->toBinary(), 'id' => $id->toBinary()]);
    }
}
