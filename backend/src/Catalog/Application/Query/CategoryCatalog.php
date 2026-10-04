<?php

namespace App\Catalog\Application\Query;

use Symfony\Component\Uid\Uuid;

interface CategoryCatalog
{
    /** @return list<CategoryView> by name */
    public function list(Uuid $companyId): array;

    /** @throws \App\Catalog\Domain\Error\CategoryNotFound */
    public function get(Uuid $companyId, Uuid $categoryId): CategoryView;
}
