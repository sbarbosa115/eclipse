<?php

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Error\CategoryNotFound;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineCategoryRepository implements CategoryRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): Category
    {
        return $this->em->getRepository(Category::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new CategoryNotFound();
    }

    public function exists(Uuid $companyId, Uuid $id): bool
    {
        return null !== $this->em->getRepository(Category::class)->findOneBy(['companyId' => $companyId, 'id' => $id]);
    }

    public function nameTaken(Uuid $companyId, string $name, ?Uuid $exceptId = null): bool
    {
        $found = $this->em->getRepository(Category::class)->findOneBy(['companyId' => $companyId, 'name' => $name]);

        return null !== $found && !($exceptId?->equals($found->id()) ?? false);
    }

    public function add(Category $category): void
    {
        $this->em->persist($category);
    }
}
