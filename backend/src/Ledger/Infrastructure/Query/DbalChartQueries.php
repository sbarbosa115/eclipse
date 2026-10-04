<?php

namespace App\Ledger\Infrastructure\Query;

use App\Ledger\Application\Query\AccountView;
use App\Ledger\Application\Query\ChartQueries;
use App\Ledger\Application\Query\Page;
use App\Ledger\Domain\Model\AccountLevel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final class DbalChartQueries implements ChartQueries
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function page(Uuid $companyId, string $query, ?string $class, int $page, int $perPage): Page
    {
        $filtered = function () use ($companyId, $query, $class): QueryBuilder {
            $qb = $this->db->createQueryBuilder()->from('ledger_account', 'a')
                ->where('a.company_id = :company')->setParameter('company', $companyId->toBinary());
            $query = trim($query);
            if ('' !== $query) {
                $like = addcslashes($query, '%_\\');
                ctype_digit($query)
                    ? $qb->andWhere('a.code LIKE :q')->setParameter('q', $like.'%')
                    : $qb->andWhere('a.name LIKE :q')->setParameter('q', '%'.$like.'%');
            }
            if (null !== $class) {
                $qb->andWhere('a.code LIKE :class')->setParameter('class', $class.'%');
            }

            return $qb;
        };

        $total = (int) $filtered()->select('COUNT(*)')->executeQuery()->fetchOne();
        $rows = $filtered()
            ->select('a.id, a.code, a.name, a.nature, a.level, a.parent_code, a.standard, a.active, a.usable_on_purchases')
            ->orderBy('a.code', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)
            ->executeQuery()->fetchAllAssociative();

        return new Page(array_map(self::view(...), $rows), $total, $page, $perPage);
    }

    /** @param array<string, mixed> $r */
    private static function view(array $r): AccountView
    {
        $level = AccountLevel::from((string) $r['level']);
        $active = (bool) $r['active'];

        return new AccountView(
            Uuid::fromBinary((string) $r['id'])->toRfc4122(),
            (string) $r['code'],
            (string) $r['name'],
            (string) $r['nature'],
            $level->value,
            null === $r['parent_code'] ? null : (string) $r['parent_code'],
            (bool) $r['standard'],
            $active,
            $active && $level->isPostable(),
            (bool) $r['usable_on_purchases'],
        );
    }
}
