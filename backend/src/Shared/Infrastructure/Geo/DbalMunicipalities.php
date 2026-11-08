<?php

namespace App\Shared\Infrastructure\Geo;

use App\Shared\Application\Geo\DepartmentView;
use App\Shared\Application\Geo\Municipalities;
use App\Shared\Application\Geo\MunicipalityView;
use Doctrine\DBAL\Connection;

final class DbalMunicipalities implements Municipalities
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function departments(): array
    {
        $rows = $this->db->fetchAllAssociative('SELECT department_code, MIN(department_name) AS department_name FROM geo_municipality GROUP BY department_code ORDER BY department_code');

        return array_map(static fn (array $r) => new DepartmentView((string) $r['department_code'], (string) $r['department_name']), $rows);
    }

    public function search(string $query, ?string $departmentCode = null, int $limit = 50): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select('code', 'name', 'department_code', 'department_name')
            ->from('geo_municipality')
            ->orderBy('department_name')->addOrderBy('name')
            ->setMaxResults(max(1, min($limit, 200)));
        $query = trim($query);
        if ('' !== $query) {
            $like = addcslashes($query, '%_\\');
            // The column's collation (utf8mb4 *_ci) ignores case and accents: "bogota" finds BOGOTÁ, D.C.
            $qb->andWhere('name LIKE :name OR code LIKE :code')
                ->setParameter('name', '%'.$like.'%')->setParameter('code', $like.'%');
        }
        if (null !== $departmentCode && '' !== $departmentCode) {
            $qb->andWhere('department_code = :department')->setParameter('department', $departmentCode);
        }

        return array_map(self::view(...), $qb->executeQuery()->fetchAllAssociative());
    }

    public function find(string $code): ?MunicipalityView
    {
        $row = $this->db->fetchAssociative('SELECT code, name, department_code, department_name FROM geo_municipality WHERE code = ?', [$code]);

        return false === $row ? null : self::view($row);
    }

    /** @param array<string, mixed> $r */
    private static function view(array $r): MunicipalityView
    {
        return new MunicipalityView((string) $r['code'], (string) $r['name'], (string) $r['department_code'], (string) $r['department_name']);
    }
}
