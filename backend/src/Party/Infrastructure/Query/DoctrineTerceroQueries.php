<?php

namespace App\Party\Infrastructure\Query;

use App\Party\Application\Query\TerceroPage;
use App\Party\Application\Query\TerceroQueries;
use App\Party\Domain\Error\TerceroNotFound;
use App\Party\Domain\Model\Tercero;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Uid\Uuid;

final class DoctrineTerceroQueries implements TerceroQueries
{
    private const ROLE_COLUMNS = ['cliente' => 'isClient', 'proveedor' => 'isSupplier', 'empleado' => 'isEmployee', 'otro' => 'isOther'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function search(Uuid $companyId, ?string $q, ?string $role, ?bool $active, int $page, int $perPage): TerceroPage
    {
        $qb = $this->em->createQueryBuilder()->select('t')->from(Tercero::class, 't')
            ->where('t.companyId = :company')->setParameter('company', $companyId, 'uuid')
            ->orderBy('t.displayName', 'ASC')->addOrderBy('t.identificationNumber', 'ASC');

        $q = null === $q ? '' : trim($q);
        if ('' !== $q) {
            // "900.123.456-1" is how people type it; the number is stored without punctuation.
            $id = (string) preg_replace('/[\s.\-]/', '', $q);
            $match = 't.displayName LIKE :q ESCAPE \'!\' OR t.tradeName LIKE :q ESCAPE \'!\'';
            $qb->setParameter('q', '%'.self::like($q).'%');
            if ('' !== $id) {
                $match .= ' OR t.identificationNumber LIKE :id ESCAPE \'!\'';
                $qb->setParameter('id', '%'.self::like($id).'%');
            }
            $qb->andWhere($match);
        }
        if (null !== $role && isset(self::ROLE_COLUMNS[$role])) {
            $qb->andWhere('t.'.self::ROLE_COLUMNS[$role].' = true');
        }
        if (null !== $active) {
            $qb->andWhere('t.active = :active')->setParameter('active', $active);
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($qb, false);

        return new TerceroPage(array_values(iterator_to_array($paginator)), \count($paginator), $page, $perPage);
    }

    public function get(Uuid $companyId, Uuid $terceroId): Tercero
    {
        return $this->em->getRepository(Tercero::class)->findOneBy(['companyId' => $companyId, 'id' => $terceroId]) ?? throw new TerceroNotFound();
    }

    /** `%`, `_` and the escape character itself match literally. */
    private static function like(string $text): string
    {
        return (string) preg_replace('/[%_!]/', '!$0', $text);
    }
}
