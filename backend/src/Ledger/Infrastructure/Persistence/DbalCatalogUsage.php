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
    /** table => the columns that hold a tax id (a line's copy of it, a product's default) */
    private const TAX_COLUMNS = [
        'quotation_line' => ['charge_tax_id', 'withholding_tax_id'],
        'sales_invoice_line' => ['charge_tax_id', 'withholding_tax_id'],
        'purchase_invoice_line' => ['charge_tax_id', 'withholding_tax_id'],
        'product' => ['charge_tax_id', 'withholding_tax_id'],
    ];

    /** The company row keeps its defaults under other column names. */
    private const COMPANY_TAX_COLUMNS = ['default_charge_tax_id', 'default_withholding_tax_id'];

    /** Tables with a payment_method_id column. */
    private const METHOD_TABLES = ['sales_invoice_payment', 'purchase_invoice_payment', 'cash_receipt', 'supplier_payment'];

    public function __construct(private readonly Connection $db)
    {
    }

    public function taxIsUsed(Uuid $companyId, Uuid $taxId): bool
    {
        return \in_array($taxId->toRfc4122(), $this->usedTaxIds($companyId), true);
    }

    public function paymentMethodIsUsed(Uuid $companyId, Uuid $paymentMethodId): bool
    {
        return \in_array($paymentMethodId->toRfc4122(), $this->usedPaymentMethodIds($companyId), true);
    }

    public function usedTaxIds(Uuid $companyId): array
    {
        $selects = [];
        foreach (self::TAX_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $selects[] = "SELECT DISTINCT $column AS id FROM $table WHERE company_id = :company AND $column IS NOT NULL";
            }
        }
        foreach (self::COMPANY_TAX_COLUMNS as $column) {
            $selects[] = "SELECT $column AS id FROM company WHERE id = :company AND $column IS NOT NULL";
        }

        return $this->ids($selects, $companyId);
    }

    public function usedPaymentMethodIds(Uuid $companyId): array
    {
        $selects = array_map(static fn (string $table) => "SELECT DISTINCT payment_method_id AS id FROM $table WHERE company_id = :company", self::METHOD_TABLES);

        return $this->ids($selects, $companyId);
    }

    /**
     * @param list<string> $selects
     *
     * @return list<string>
     */
    private function ids(array $selects, Uuid $companyId): array
    {
        /** @var list<string> $binary */
        $binary = $this->db->fetchFirstColumn(implode(' UNION ', $selects), ['company' => $companyId->toBinary()]);

        return array_map(static fn (string $id) => Uuid::fromBinary($id)->toRfc4122(), $binary);
    }
}
