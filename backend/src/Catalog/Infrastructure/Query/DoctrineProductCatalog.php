<?php

namespace App\Catalog\Infrastructure\Query;

use App\Catalog\Application\ProductReferences;
use App\Catalog\Application\Query\ProductCatalog;
use App\Catalog\Application\Query\ProductPage;
use App\Catalog\Application\Query\ProductView;
use App\Catalog\Domain\Error\ProductNotFound;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductType;
use App\Catalog\Domain\Pricing\PriceNetOfTax;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Application\Query\TaxView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineProductCatalog implements ProductCatalog
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LedgerCatalog $ledger,
    ) {
    }

    public function get(Uuid $companyId, Uuid $productId): ProductView
    {
        $p = $this->em->getRepository(Product::class)->findOneBy(['companyId' => $companyId, 'id' => $productId]) ?? throw new ProductNotFound();

        return $this->views($companyId, [$p])[0];
    }

    public function search(Uuid $companyId, ?string $query, ?string $type, ?bool $active, int $page, int $perPage): ProductPage
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $qb = $this->em->createQueryBuilder()->from(Product::class, 'p')
            ->where('p.companyId = :company')->setParameter('company', $companyId, 'uuid');
        $query = trim((string) $query);
        if ('' !== $query) {
            $qb->andWhere('p.code LIKE :q OR p.name LIKE :q')->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }
        if (null !== $type && null !== ProductType::tryFrom($type)) {
            $qb->andWhere('p.type = :type')->setParameter('type', ProductType::from($type));
        }
        if (null !== $active) {
            $qb->andWhere('p.active = :active')->setParameter('active', $active);
        }

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        /** @var list<Product> $products */
        $products = $qb->select('p')->orderBy('p.name', 'ASC')->addOrderBy('p.code', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return new ProductPage($this->views($companyId, $products), $total, $page, $perPage);
    }

    /**
     * @param list<Product> $products
     *
     * @return list<ProductView>
     */
    private function views(Uuid $companyId, array $products): array
    {
        if ([] === $products) {
            return [];
        }
        $taxes = [];
        foreach ($this->ledger->taxes($companyId, null, false) as $tax) {
            $taxes[$tax->id] = $tax;
        }
        $categories = [];
        foreach ($this->em->getRepository(Category::class)->findBy(['companyId' => $companyId]) as $category) {
            $categories[$category->id()->toRfc4122()] = $category->name();
        }

        return array_map(fn (Product $p) => $this->view($p, $taxes[$p->chargeTaxId()?->toRfc4122()] ?? null, $categories), $products);
    }

    /**
     * @param array<string, string> $categories names by id
     */
    private function view(Product $p, ?TaxView $charge, array $categories): ProductView
    {
        $categoryId = $p->categoryId()?->toRfc4122();

        try {
            $net = PriceNetOfTax::of($p->salePrice(), $p->priceIncludesTax(), null === $charge ? null : ProductReferences::rateOf($charge))->toString();
        } catch (\InvalidArgumentException) {
            // A tax per unit raised above the price since the product was saved leaves nothing to charge.
            $net = '0.0000';
        }

        return new ProductView(
            $p->id()->toRfc4122(),
            $p->type()->value,
            $p->code(),
            $p->name(),
            $p->description(),
            $p->unitCode(),
            $p->salePrice()->toString(),
            $p->priceIncludesTax(),
            $p->chargeTaxId()?->toRfc4122(),
            $p->withholdingTaxId()?->toRfc4122(),
            $p->revenueAccountId()?->toRfc4122(),
            $p->expenseAccountId()?->toRfc4122(),
            $p->isActive(),
            $categoryId,
            null === $categoryId ? null : ($categories[$categoryId] ?? null),
            $net,
        );
    }
}
