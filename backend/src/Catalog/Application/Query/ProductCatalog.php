<?php

namespace App\Catalog\Application\Query;

use Symfony\Component\Uid\Uuid;

interface ProductCatalog
{
    /** @throws \App\Catalog\Domain\Error\ProductNotFound */
    public function get(Uuid $companyId, Uuid $productId): ProductView;
}
