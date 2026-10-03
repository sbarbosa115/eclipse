<?php

namespace App\Catalog\Infrastructure\Query;

use App\Catalog\Application\Query\CategoryCatalog;
use App\Catalog\Application\Query\CategoryView;
use App\Catalog\Domain\Error\CategoryNotFound;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineCategoryCatalog implements CategoryCatalog
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function list(Uuid $companyId): array
    {
        $counts = $this->counts($companyId);
        $views = [];
        foreach ($this->em->getRepository(Category::class)->findBy(['companyId' => $companyId], ['name' => 'ASC']) as $category) {
            $views[] = new CategoryView($category->id()->toRfc4122(), $category->name(), $counts[$category->id()->toRfc4122()] ?? 0);
        }

        return $views;
    }

    public function get(Uuid $companyId, Uuid $categoryId): CategoryView
    {
        $category = $this->em->getRepository(Category::class)->findOneBy(['companyId' => $companyId, 'id' => $categoryId]) ?? throw new CategoryNotFound();

        return new CategoryView($category->id()->toRfc4122(), $category->name(), $this->counts($companyId)[$category->id()->toRfc4122()] ?? 0);
    }

    /**
     * @return array<string, int> product count by category id
     */
    private function counts(Uuid $companyId): array
    {
        /** @var list<array{categoryId: Uuid, n: int|string}> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('p.categoryId AS categoryId', 'COUNT(p.id) AS n')->from(Product::class, 'p')
            ->where('p.companyId = :company')->andWhere('p.categoryId IS NOT NULL')
            ->setParameter('company', $companyId, 'uuid')
            ->groupBy('p.categoryId')->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['categoryId']->toRfc4122()] = (int) $row['n'];
        }

        return $counts;
    }
}
