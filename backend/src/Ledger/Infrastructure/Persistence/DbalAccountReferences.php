<?php

namespace App\Ledger\Infrastructure\Persistence;

use App\Ledger\Application\Port\AccountReferences;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/** Counts the rows of Catalog's and Party's tables that point at an account (their foreign keys to ledger_account). */
final class DbalAccountReferences implements AccountReferences
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function products(Uuid $companyId, Uuid $accountId): int
    {
        return $this->count('product', ['revenue_account_id', 'expense_account_id'], $companyId, $accountId);
    }

    public function terceros(Uuid $companyId, Uuid $accountId): int
    {
        return $this->count('tercero', ['receivable_account_id', 'payable_account_id'], $companyId, $accountId);
    }

    /**
     * @param list<string> $columns
     */
    private function count(string $table, array $columns, Uuid $companyId, Uuid $accountId): int
    {
        $any = implode(' OR ', array_map(static fn (string $c) => "$c = :account", $columns));

        return (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM $table WHERE company_id = :company AND ($any)",
            ['company' => $companyId->toBinary(), 'account' => $accountId->toBinary()],
        );
    }
}
