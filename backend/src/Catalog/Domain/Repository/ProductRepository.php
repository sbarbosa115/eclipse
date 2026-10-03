<?php

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Product;
use Symfony\Component\Uid\Uuid;

interface ProductRepository
{
    /** @throws \App\Catalog\Domain\Error\ProductNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): Product;

    /** Is this código used by a product of the company other than $exceptId? */
    public function codeTaken(Uuid $companyId, string $code, ?Uuid $exceptId = null): bool;

    public function add(Product $product): void;

    public function remove(Product $product): void;
}
