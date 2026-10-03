<?php

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Error\ProductNotFound;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new ProductNotFound();
    }

    public function codeTaken(Uuid $companyId, string $code, ?Uuid $exceptId = null): bool
    {
        $found = $this->em->getRepository(Product::class)->findOneBy(['companyId' => $companyId, 'code' => $code]);

        return null !== $found && !($exceptId?->equals($found->id()) ?? false);
    }

    public function add(Product $product): void
    {
        $this->em->persist($product);
    }

    public function remove(Product $product): void
    {
        $this->em->remove($product);
    }
}
