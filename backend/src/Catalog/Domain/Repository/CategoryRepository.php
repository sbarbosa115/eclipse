<?php

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Category;
use Symfony\Component\Uid\Uuid;

interface CategoryRepository
{
    /** @throws \App\Catalog\Domain\Error\CategoryNotFound */
    public function get(Uuid $companyId, Uuid $id): Category;

    public function exists(Uuid $companyId, Uuid $id): bool;

    /** Is this name used by a category of the company other than $exceptId? */
    public function nameTaken(Uuid $companyId, string $name, ?Uuid $exceptId = null): bool;

    public function add(Category $category): void;
}
