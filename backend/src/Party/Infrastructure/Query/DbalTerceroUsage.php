<?php

namespace App\Party\Infrastructure\Query;

use App\Party\Application\Port\TerceroUsage;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Looks in every table that carries a `tercero_id` and a `company_id` (documents, receivables and payables, journal
 * lines, receipts…), found in the schema itself so a table another context adds is covered without a change here.
 */
final class DbalTerceroUsage implements TerceroUsage
{
    /** @var list<string>|null */
    private ?array $tables = null;

    public function __construct(private readonly Connection $db)
    {
    }

    public function isReferenced(Uuid $companyId, Uuid $terceroId): bool
    {
        foreach ($this->tables() as $table) {
            $found = $this->db->fetchOne(
                \sprintf('SELECT 1 FROM `%s` WHERE company_id = ? AND tercero_id = ? LIMIT 1', $table),
                [$companyId->toBinary(), $terceroId->toBinary()],
            );
            if (false !== $found) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function tables(): array
    {
        return $this->tables ??= array_values(array_map('strval', $this->db->fetchFirstColumn(
            'SELECT t.table_name FROM information_schema.columns t
             JOIN information_schema.columns c ON c.table_schema = t.table_schema AND c.table_name = t.table_name AND c.column_name = \'company_id\'
             WHERE t.table_schema = DATABASE() AND t.column_name = \'tercero_id\' AND t.table_name NOT IN (\'tercero\', \'tercero_contact\')',
        )));
    }
}
