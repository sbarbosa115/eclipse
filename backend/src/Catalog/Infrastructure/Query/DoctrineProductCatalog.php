<?php

namespace App\Catalog\Infrastructure\Query;

use App\Catalog\Application\Query\ProductCatalog;
use App\Catalog\Application\Query\ProductView;
use App\Catalog\Domain\Error\ProductNotFound;
use App\Catalog\Domain\Model\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineProductCatalog implements ProductCatalog
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $productId): ProductView
    {
        $p = $this->em->getRepository(Product::class)->findOneBy(['companyId' => $companyId, 'id' => $productId]) ?? throw new ProductNotFound();

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
        );
    }
}
