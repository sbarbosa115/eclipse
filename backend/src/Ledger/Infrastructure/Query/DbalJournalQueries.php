<?php

namespace App\Ledger\Infrastructure\Query;

use App\Ledger\Application\Query\JournalEntryView;
use App\Ledger\Application\Query\JournalFilter;
use App\Ledger\Application\Query\JournalLineView;
use App\Ledger\Application\Query\JournalQueries;
use App\Ledger\Application\Query\Page;
use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * Three queries a page, whatever its size: the count, the page's entries, and all their lines (with the account's
 * name and the tercero's, joined: the tercero name is shared reference data the libro diario shows, read here rather
 * than one call per line).
 */
final class DbalJournalQueries implements JournalQueries
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function page(Uuid $companyId, JournalFilter $filter, int $page, int $perPage): Page
    {
        $filtered = function () use ($companyId, $filter): QueryBuilder {
            $qb = $this->db->createQueryBuilder()->from('journal_entry', 'e')
                ->where('e.company_id = :company')->setParameter('company', $companyId->toBinary());
            if (null !== $filter->from) {
                $qb->andWhere('e.entry_date >= :from')->setParameter('from', $filter->from->format('Y-m-d'));
            }
            if (null !== $filter->to) {
                $qb->andWhere('e.entry_date <= :to')->setParameter('to', $filter->to->format('Y-m-d'));
            }
            if (null !== $filter->accountCode) {
                $qb->andWhere('EXISTS (SELECT 1 FROM journal_line fa WHERE fa.entry_id = e.id AND fa.company_id = e.company_id AND fa.account_code LIKE :account)')
                    ->setParameter('account', addcslashes($filter->accountCode, '%_\\').'%');
            }
            if (null !== $filter->terceroId) {
                $qb->andWhere('EXISTS (SELECT 1 FROM journal_line ft WHERE ft.entry_id = e.id AND ft.company_id = e.company_id AND ft.tercero_id = :tercero)')
                    ->setParameter('tercero', $filter->terceroId->toBinary());
            }

            return $qb;
        };

        $total = (int) $filtered()->select('COUNT(*)')->executeQuery()->fetchOne();
        $entries = $filtered()
            ->select('e.id, e.number, e.entry_date, e.source_type, e.source_id, e.source_number, e.description, e.reverses_id')
            ->orderBy('e.entry_date', 'ASC')->addOrderBy('e.number', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)
            ->executeQuery()->fetchAllAssociative();

        $lines = [];
        if ([] !== $entries) {
            $rows = $this->db->fetchAllAssociative(
                'SELECT l.entry_id, l.account_id, l.account_code, a.name AS account_name, l.tercero_id, t.display_name AS tercero_name, l.debit, l.credit, l.description
                 FROM journal_line l
                 JOIN ledger_account a ON a.id = l.account_id
                 LEFT JOIN tercero t ON t.id = l.tercero_id AND t.company_id = l.company_id
                 WHERE l.company_id = :company AND l.entry_id IN (:entries)
                 ORDER BY l.position',
                ['company' => $companyId->toBinary(), 'entries' => array_column($entries, 'id')],
                ['entries' => ArrayParameterType::BINARY],
            );
            foreach ($rows as $r) {
                $lines[(string) $r['entry_id']][] = new JournalLineView(
                    Uuid::fromBinary((string) $r['account_id'])->toRfc4122(),
                    (string) $r['account_code'],
                    (string) $r['account_name'],
                    null === $r['tercero_id'] ? null : Uuid::fromBinary((string) $r['tercero_id'])->toRfc4122(),
                    null === $r['tercero_name'] ? null : (string) $r['tercero_name'],
                    Money::of((string) $r['debit'])->toString(),
                    Money::of((string) $r['credit'])->toString(),
                    null === $r['description'] ? null : (string) $r['description'],
                );
            }
        }

        $views = [];
        foreach ($entries as $e) {
            $entryLines = $lines[(string) $e['id']] ?? [];
            $views[] = new JournalEntryView(
                Uuid::fromBinary((string) $e['id'])->toRfc4122(),
                (int) $e['number'],
                substr((string) $e['entry_date'], 0, 10),
                (string) $e['source_type'],
                Uuid::fromBinary((string) $e['source_id'])->toRfc4122(),
                (string) $e['source_number'],
                (string) $e['description'],
                null === $e['reverses_id'] ? null : Uuid::fromBinary((string) $e['reverses_id'])->toRfc4122(),
                Money::sum(...array_map(static fn (JournalLineView $l) => Money::of($l->debit), $entryLines))->toString(),
                Money::sum(...array_map(static fn (JournalLineView $l) => Money::of($l->credit), $entryLines))->toString(),
                $entryLines,
            );
        }

        return new Page($views, $total, $page, $perPage);
    }
}
