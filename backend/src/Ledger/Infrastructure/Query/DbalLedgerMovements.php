<?php

namespace App\Ledger\Infrastructure\Query;

use App\Ledger\Application\Query\AccountMovement;
use App\Ledger\Application\Query\LedgerMovements;
use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * One grouped query over journal_line + journal_entry (indexed by company and date): the database adds, PHP rolls up.
 */
final class DbalLedgerMovements implements LedgerMovements
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function byAccount(Uuid $companyId, ?\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $start = $from?->format('Y-m-d') ?? '0001-01-01';
        $rows = $this->db->fetchAllAssociative(
            'SELECT l.account_code AS code,
                    SUM(CASE WHEN e.entry_date < :start THEN l.debit - l.credit ELSE 0 END) AS opening,
                    SUM(CASE WHEN e.entry_date >= :start THEN l.debit ELSE 0 END) AS debit,
                    SUM(CASE WHEN e.entry_date >= :start THEN l.credit ELSE 0 END) AS credit
             FROM journal_line l JOIN journal_entry e ON e.id = l.entry_id
             WHERE l.company_id = :company AND e.company_id = :company AND e.entry_date <= :end
             GROUP BY l.account_code
             ORDER BY l.account_code',
            ['company' => $companyId->toBinary(), 'start' => $start, 'end' => $to->format('Y-m-d')],
        );

        return array_map(static fn (array $r) => new AccountMovement((string) $r['code'], Money::of((string) $r['opening']), Money::of((string) $r['debit']), Money::of((string) $r['credit'])), $rows);
    }

    public function accounts(Uuid $companyId, array $codes): array
    {
        if ([] === $codes) {
            return [];
        }
        $rows = $this->db->fetchAllAssociative(
            'SELECT code, name, level, nature FROM ledger_account WHERE company_id = :company AND code IN (:codes)',
            ['company' => $companyId->toBinary(), 'codes' => $codes],
            ['codes' => ArrayParameterType::STRING],
        );

        $accounts = [];
        foreach ($rows as $r) {
            $accounts[(string) $r['code']] = ['name' => (string) $r['name'], 'level' => (string) $r['level'], 'nature' => (string) $r['nature']];
        }

        return $accounts;
    }
}
