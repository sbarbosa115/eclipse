<?php

namespace App\Catalog\Infrastructure\Query;

use App\Catalog\Application\Port\ProductUsage;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Asks the line tables of the documents that carry a product_id, with SQL, so Catalog never names a Sales or Purchasing
 * class. A new kind of document line with a product adds its table here.
 */
final class DatabaseProductUsage implements ProductUsage
{
    private const LINE_TABLES = ['quotation_line', 'sales_invoice_line', 'purchase_invoice_line'];

    public function __construct(private readonly Connection $db)
    {
    }

    public function isUsed(Uuid $companyId, Uuid $productId): bool
    {
        foreach (self::LINE_TABLES as $table) {
            $found = $this->db->fetchOne(\sprintf('SELECT 1 FROM %s WHERE company_id = ? AND product_id = ? LIMIT 1', $table), [$companyId->toBinary(), $productId->toBinary()]);
            if (false !== $found) {
                return true;
            }
        }

        return false;
    }
}
