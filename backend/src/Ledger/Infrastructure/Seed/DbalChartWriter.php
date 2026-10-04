<?php

namespace App\Ledger\Infrastructure\Seed;

use App\Ledger\Application\Port\ChartWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Multi-row INSERTs, 500 rows at a time, straight through DBAL: ~2 500 accounts in a few statements instead of 2 500
 * ORM inserts. Flushes what the sign-up has persisted so far first, so the company row the accounts reference exists.
 */
final class DbalChartWriter implements ChartWriter
{
    private const CHUNK = 500;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function write(Uuid $companyId, array $accounts, array $rules): void
    {
        $this->em->flush();
        $company = $companyId->toBinary();

        $rows = [];
        foreach ($accounts as $a) {
            $rows[] = [$a->id()->toBinary(), $company, $a->code(), $a->name(), $a->nature()->value, $a->level()->value, $a->parentCode(), (int) $a->isStandard(), (int) $a->isActive(), (int) $a->isUsableOnPurchases()];
        }
        $this->insert('ledger_account', ['id', 'company_id', 'code', 'name', 'nature', 'level', 'parent_code', 'standard', 'active', 'usable_on_purchases'], $rows);

        $rows = [];
        foreach ($rules as $r) {
            $rows[] = [$r->id()->toBinary(), $company, $r->concept()->value, $r->accountId()->toBinary()];
        }
        $this->insert('posting_rule', ['id', 'company_id', 'concept', 'account_id'], $rows);

        $this->insert('ledger_settings', ['company_id', 'locked_until'], [[$company, null]]);
    }

    /**
     * @param list<string>                $columns
     * @param list<list<string|int|null>> $rows
     */
    private function insert(string $table, array $columns, array $rows): void
    {
        $db = $this->em->getConnection();
        $row = '('.implode(', ', array_fill(0, \count($columns), '?')).')';
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $db->executeStatement(
                \sprintf('INSERT INTO %s (%s) VALUES %s', $table, implode(', ', $columns), implode(', ', array_fill(0, \count($chunk), $row))),
                array_merge(...$chunk),
            );
        }
    }
}
